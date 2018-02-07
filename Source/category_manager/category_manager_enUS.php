<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$exporta1 = "List all products with their categories";
$exporta2 = "List all categories without roots";
$exporta3 = "List all categories with roots";
$exporta4 = "List products with their prices";
$exporta5 = "List all sku,name,categories";

$importa1 = "Associate products with categories";
$importa2 = "Associate products with prices";
$error1 = "Error: Could not find Magento installation";
$error2 = "File not sent";
$error3 = "Could not retrieve sent file, check filesize";
$error4 = "File header doesn't match expected value of %s";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
	function getMagentoPath() {
		$tests = [".", "..", "../..", "public_html", "../public_html", "web", "../web"];
		foreach ($tests as $path)
			if (is_dir($path) && file_exists($path."/index.php") && is_dir($path."/app/") && is_dir($path."/var/") && is_dir($path."/media/") && is_dir($path."/skin/"))
				return $path;
	}
	if (!($magentoPath = getMagentoPath())) {
		echo $error1;
		die();
	}
	require_once implode(DIRECTORY_SEPARATOR, [$magentoPath, "app", "Mage.php"]);
	Mage::setIsDeveloperMode(true);
	umask(0);
	Mage::app('admin');
	Mage::register('isSecureArea', 1);
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	if ($_POST['submit'] == $importa1 || $_POST['submit'] == $importa2) {
		echo "<pre>";
		if (!(isset($_FILES['userfile'])) || !(isset($_FILES['userfile']['name'])) || empty($_FILES['userfile']['name'])) {
			echo "$error2\n";
		} else if (!isset($_FILES['userfile']['tmp_name']) || empty($_FILES['userfile']['tmp_name'])) {
			echo $error3;
		} else {
			echo "Reading file ".$_FILES['userfile']['name']." with ".$_FILES['userfile']['size']." bytes\n";
			$fp = fopen($_FILES['userfile']['tmp_name'], 'rb');
			$count = 0;
			$error = false;
			$importMode = 0;
			$ignoreErrors = (isset($_POST['isskiperrors']) && !empty(isset($_POST['isskiperrors'])));
			$resource = Mage::getSingleton('core/resource');
			$db_read = $resource->getConnection('core_read');
			$catIds = $db_read->query("SELECT entity_id, sku FROM " . $resource->getTableName("catalog_product_entity"));
			$entityIdBySku = [];
			$missingProducts = [];
			$emptyCategoryList = [];
			foreach ($catIds as $catId) {
				$entityIdBySku[(string)$catId['sku']] = intval($catId['entity_id']);
			}
			$productResource = Mage::getModel('catalog/product');
			function set_product_category($entity_id, $category_list) {
				global $productResource;
				$product = $productResource->load($entity_id);
				$product->setCategoryIds($category_list);
				$product->save();
				if (!empty($category_list) && rand(1,2)==1) {
					echo "set $category_list\n";
					//echo "Set product $entity_id with categories $category_list\n";
				}
			}
			function set_product_price($entity_id, $price) {
				global $productResource;
				$product = $productResource->load($entity_id);
				$product->setPrice($price);
				$product->save();
				//echo "Set product $entity_id price to $price\n";
			}
			function set_product_price_special($entity_id, $price, $special) {
				global $productResource;
				$product = $productResource->load($entity_id);
				$product->setPrice($price);
				$product->setSpecialPrice($special);
				$product->save();
				//echo "Set product $entity_id price to $price and special to $special\n";
			}
			//var_dump($entityIdBySku);
			set_time_limit(600);
			while ( ($line = fgets($fp)) !== false) {
				if ($count == 0) {
					if ($_POST['submit'] == $importa1) {
						if ($line != "sku,name,category\n" || $line != "sku,name,categories\n") {
							$importMode = 5;
						} else if (substr($line,0,12) == "sku,category") {
							$importMode = 1;
						} else {
							$error = true;
							printf("<span style='color:red'>Error:</span> ".$error4. ", it is \"".$line.'"', "sku,category or sku,name,category");
							break;
						}
					} else if ($_POST['submit'] == $importa2) {
						if ($line == "sku,price\n") {
							$importMode = 2;
						} else if ($line == "sku,price,special_price\n") {
							$importMode = 3;
						} else {
							$error = true;
							printf("<span style='color:red'>Error:</span> ".$error4, "sku,price or sku,price,special_price");
							break;
						}
					} else {
						$error = true;
						printf("Unhandled import type");
						break;
					}
				} else {
					$lineSku = substr($line, 0, strpos($line, ","));
					if ($ignoreErrors || $importMode == 5) {
						if ($importMode == 1 || $importMode == 5) {
							if ($importMode == 5) {
								$lineCategory = trim(preg_replace('/\s+/', '', substr($line, strrpos($line, ",")+1)),'"');
							} else {
								$lineCategory = trim(preg_replace('/\s+/', '', substr($line, strpos($line, ",")+1)),'"');
							}
							if (empty($lineCategory)) {
								array_push($emptyCategoryList, $lineSku);
							}
							if (isset($entityIdBySku[$lineSku])) {
								set_product_category($entityIdBySku[$lineSku], $lineCategory);
							}
						}
					}
					if ($importMode == 1 || $importMode == 2 || $importMode == 3) {
						if (!isset($entityIdBySku[$lineSku])) {
							array_push($missingProducts, $lineSku);
						}
					}
				}
				$count++;
			}
			if ($ignoreErrors && count($missingProducts)) {
				echo "A total of ".count($missingProducts)." products from the file could not be processed because they don't exist in Magento:\n";
				echo implode(",", $missingProducts)."\n";
			} else if (!$ignoreErrors && count($missingProducts)) {
				echo "Nothing was imported because ".count($missingProducts)." products from the file don't exist in Magento:\n";
				echo implode(",", $missingProducts)."\n";
				$error = true;
			}
			if ($importMode == 1 && count($emptyCategoryList)) {
				echo "A total of ".count($emptyCategoryList)." products were left without any categories:\n";
				echo implode(",", $emptyCategoryList)."\n";
			}
			if (!$error && !$ignoreErrors) {
				fseek($fp, 0, SEEK_SET);
			}
			if (!$error) {
				/*
				$indexCollection = Mage::getModel('index/process')->getCollection();
				foreach ($indexCollection as $index) {
					$index->reindexAll();
				}*/
			}
			fclose($fp);
		}
		echo "</pre>";
	} else if ($_POST['submit'] == $exporta5) {
		$selectedAttributes = ['entity_id', 'sku'];
		if ($_POST['submit'] == $exporta5) {
			$selectedAttributes[] = 'name';
			$selectedAttributes[] = 'category_ids';
		}
		$collection=Mage::getModel('catalog/product')->getCollection()
			->addAttributeToSelect($selectedAttributes);

		function get_single_csv_line_from_product($product) {
			global $exporta5;
			if ($_POST['submit'] == $exporta5) {
				return [
					$product->getSku(),
					$product->getName(),
					implode(",",$product->getCategoryIds())
				];
			}
			return ["unknown option"];
		}
		if ($_POST['submit'] == $exporta5) {
			$ret = "sku,name,categories";
		} else {
			$ret = "unknown header";
		}
		$ret .= "\n";
		foreach ($collection as $product) {
			$arr = get_single_csv_line_from_product($product);
			if (is_array($arr)) {
				$clean = [];
				foreach ($arr as $columnRaw) {
					$column = (string)$columnRaw;
					$hasQuotes = (strpos($column, '"') !== false);
					$hasComma = (strpos($column, ',') !== false);
					if (($hasQuotes && $hasComma) || ($hasQuotes && !$hasComma)) {
						$clean[] = '"'.str_replace('"', '""', $column).'"';
					} else if (!$hasQuotes && $hasComma) {
						$clean[] = '"'.$column.'"';
					} else if (!$hasQuotes && !$hasComma) {
						$clean[] = $column;
					} else {
						$clean[] = "unexpected value";
					}
				}
				$ret .= implode(",", $clean)."\n";
			} else if (is_string($arr)) {
				$ret .= $arr."\n";
			} else {
				$ret .= "invalid type\n";
			}
		}
		if (isset($_POST['isdownload']) && $_POST['isdownload']) {
			header('Content-Type: application/csv');
			header('Content-Length: '.strlen( $ret ));
			header('Content-disposition: inline; filename="lista_'.strtolower(Mage::getStoreConfig('general/store_information/name')).'_'.gmdate('dmY').'.csv"');
			header('Cache-Control: public, must-revalidate, max-age=0');
			header('Pragma: public');
			header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
			header('Last-Modified: '.gmdate('D, d m Y H:i:s').' GMT');
			echo $ret;
		} else {
			echo "<pre>".$ret."</pre>";
		}
	} else if ($_POST['submit'] == $exporta1 || $_POST['submit'] == $exporta4) {
		function get_sku_list() {
			global $exporta1, $exporta4;
			$products = Mage::getModel('catalog/product')->getCollection();
			$products->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns('*');
			$result = [];
			foreach ($products as $product) {
				if ($_POST['submit'] == $exporta4) {
					array_push($result, [$product->getSku(),$product->getData('qty'),$product->getSpecialPrice()]);
				} else {
					array_push($result, [$product->getSku(),'"'.implode(",",$product->getCategoryIds()).'"']);
				}
			}
			return $result;
		}
		if ($_POST['submit'] == $exporta4) {
			$ret = "sku,price,special_price\n";
		} else if ($_POST['submit'] == $exporta5) {
			$ret = "sku,name,category\n";
		} else {
			$ret = "sku,category\n";
		}
		foreach (get_sku_list() as $line) {
			$ret .= implode(",",$line)."\n";
		}
		if (isset($_POST['isdownload']) && $_POST['isdownload']) {
			header('Content-Type: application/csv');
			header('Content-Length: '.strlen( $ret ));
			header('Content-disposition: inline; filename="lista_'.strtolower(Mage::getStoreConfig('general/store_information/name')).'_'.gmdate('dmY').'.csv"');
			header('Cache-Control: public, must-revalidate, max-age=0');
			header('Pragma: public');
			header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
			header('Last-Modified: '.gmdate('D, d m Y H:i:s').' GMT');
			echo $ret;
		} else {
			echo "<pre>".$ret."</pre>";
		}
	} else if ($_POST['submit'] == $exporta2 || $_POST['submit'] == $exporta3) {
		function get_path_categories() {
			global $exporta1, $exporta2, $exporta3;
			$resource = Mage::getSingleton('core/resource');
			$db_read = $resource->getConnection('core_read');
			$entityPath = $db_read->query("SELECT entity_id, path FROM " . $resource->getTableName("catalog_category_entity") . " WHERE entity_id>0 ORDER BY entity_id ASC");
			$entityNames = $db_read->query("SELECT entity_id, value FROM " . $resource->getTableName("catalog_category_entity_varchar") . " WHERE entity_id>0 and attribute_id=41 ORDER BY entity_id ASC");
			$categorias = [];
			foreach ($entityNames as $row) {
				$categorias[$row['entity_id']] = [];
				$categorias[$row['entity_id']]["name"] = empty($row["value"])?"####":$row["value"];
			}
			foreach ($entityPath as $row) {
				$catIds = explode("/",$row["path"]);
				$catNames = [];
				foreach ($catIds as $index=>$catId) {
					if ($index < 1 && $_POST['submit'] == $exporta2) {
						continue;
					}
					array_push($catNames, isset($categorias[$catId])?$categorias[$catId]["name"]:"####");
				}
				$categorias[$row['entity_id']]["path"] = implode(",", $catNames);
			}
			return $categorias;
		}
		$cats = get_path_categories();
		$ret = "id,path\n";
		foreach ($cats as $id=>$cat) {
			if ($id <= 2 && $_POST['submit'] == $exporta2) {
				continue;
			}
			$ret .= $id.",\"".$cat["path"]."\"\n";
		}
		if (isset($_POST['isdownload'])&&$_POST['isdownload']) {
			header('Content-Type: application/csv');
			header('Content-Length: '.strlen( $ret ));
			header('Content-disposition: inline; filename="lista_'.strtolower(Mage::getStoreConfig('general/store_information/name')).'_'.gmdate('dmY').'.csv"');
			header('Cache-Control: public, must-revalidate, max-age=0');
			header('Pragma: public');
			header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
			header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
			echo $ret;
		} else {
			echo "<pre>".$ret."</pre>";
		}
	}
	exit(0);
}?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>External Magento Category Manager Tool</title>
		<style>
			html, body, body > div { background-color: #f7f7f7; margin: 0; padding: 0; width: 100vw; height: 100vh; }
			body > div { display: flex; align-items: center; justify-content: center; }
			body > div > form { color: #292929; background-color: #fff; /*border: 1px solid #AAA;*/ display: flex; flex-wrap: wrap; box-shadow: 0 1px 2px rgba(0,0,0,.5); max-width:550px; padding: 5px 0; }
			/* form label { display: block; } */
			form input { color: #292929; }
			form input[type="text"] { width: calc(100% - 6px); padding-left:2px; }
			form input[type="submit"] { margin: 6px 0 0 0; display: inline-block; }
			form textarea { width: calc(100% - 6px) !important; padding-left:2px; max-width: 480px; white-space: nowrap; height:33px !important; }
			form select { width: 100%; }
			form > .half { flex-basis: 50%; }
			form > .full { flex-basis: 100%; }
			form .pad { display: flex; flex-wrap:wrap; padding: 5px 10px; }
			form .pad > * { flex-basis: 100%; }
			form .pad > .hinted { flex-basis: 90.7%; }
			form > h1 { text-align: center; font-size: 22px; padding: 0; margin: 12px 0 8px 0; flex-basis: 100%; }
			#overlay { display: none; background-color: rgba(127, 127, 127, 0.6); position: absolute; left: 0; top: 0; right: 0; bottom: 0; color: white; z-index: 20; }
			#overlay > .shadow { /*filter: drop-shadow(0 1px 2px rgba(0,0,0,.9));*/ filter: drop-shadow(0 0px 2px rgba(0,0,0,.7)) drop-shadow(0 1px 2px rgba(0,0,0,.6)); font-size: 16px; font-family: monospace; }
			.hint { background-color: #FFF; padding: 2px; margin-left: 3px; font-size:12px; font-family: monospace; box-shadow: 0 1px 2px rgba(0,0,0,.5); min-width: 16px; min-height: 16px; max-width: 16px; max-height: 16px; display: inline-block; overflow: hidden; z-index: 10; color: white; margin-top: 5px; }
			.hint:before { width: 14px; height: 14px; border: 1px dashed #999; color: #777; content: "?"; display: inline-block; font-size: 14px; line-height: 14px; text-align: center; margin-right: 4px; }
			#validation-errors > div, .errors > div { color:red; font-weight:bold; }
			.success > div { color:green; font-weight:bold; }
			.credits { position: relative; bottom: -8px; font-size: 12px; height: 0; width: 100%; text-align: right; }
			form h2 { font-size: 16px; text-align: center; margin: 8px 0; }
			.hint-tooltip { max-width: 220px; display: inline-block; background-color: #eee; padding: 5px 10px; border: 1px solid #AAA; position: fixed; z-index: 15; pointer-events: none; line-height:20px; }
		</style>
	</head>
	<body>
		<div>
			<form target="_blank" method="post" enctype="multipart/form-data">
				<h1>External Magento Category Manager Tool</h1>
				<div class="half">
					<a class="pad">
						<h2>Export</h2>
						<div class="checkbox-wrapper" style="margin-bottom: 1px;">
							<input type="checkbox" name="isdownload" value="download" checked>
							<label for="isdownload">Download the Result</label>
							<span class="hint">Choose between downloading or just viewing the results in the browser</span>
						</div>
						<input type="submit" name="submit" value="<?php echo $exporta3 ?>">
						<input type="submit" name="submit" value="<?php echo $exporta2 ?>" class="hinted">
						<span class="hint">Doesn't export the Root Category on magento at the beggining of every path</span>
						<input type="submit" name="submit" value="<?php echo $exporta5 ?>">
						<input type="submit" name="submit" value="<?php echo $exporta1 ?>">
						<input type="submit" name="submit" value="<?php echo $exporta4 ?>">
					</a>
				</div>
				<div class="half">
					<a class="pad">
						<h2>Import</h2>
    					<input type="hidden" name="MAX_FILE_SIZE" value="600000" />
						<input name="userfile" type="file">
						<div class="checkbox-wrapper" style="margin-bottom: 1px;">
							<input type="checkbox" name="isskiperrors" value="skiperrors" checked>
							<label for="isskiperrors">Skip missing products</label>
							<span class="hint">If any product in the file doesn't exist the importing process will not start unless you check this option</span>
						</div>
						<input type="submit" class="hinted" name="submit" value="<?php echo $importa1; ?>">
						<span class="hint">Send a .csv with skus and category id to associate, file header should be:<br>sku,category<br>If a product has multiple categories, they must be separated by a comma.</span>
						<input type="submit" class="hinted" name="submit" value="<?php echo $importa2; ?>">
						<span class="hint">Envie uma tabela .csv com os skus e preços dos produtos (com decimais separados por ponto) para associar, cabeçalho deve ser:<br>sku,price</span>
					</a>
				</div>
				<div class="full"><a class="pad" id="result-errors"></a></div>
				<div class="credits">This tool was developed by Fastcompras for Magento 1.9.2 (20/12/2017)</div>
			</form>
		</div>
		<script>
			function hintEnter(ev) {
				var el = ev.target.parentNode.getElementsByClassName("hint-tooltip")[0];
				var rect = ev.target.getBoundingClientRect();
				var created = false;
				if (!el) {
					created = true;
					el = document.createElement("div");
					el.setAttribute("class", "hint-tooltip");
				}
				el.innerHTML = ev.target.innerHTML;
				el.setAttribute("style", "top: "+rect.top+"px; left: "+Math.min(rect.left, window.innerWidth-200)+"px;");
				if (created) {
					ev.target.parentNode.appendChild(el);
				}
			}
			function hintLeave(ev) {
				var elements = ev.target.parentNode.getElementsByClassName("hint-tooltip");
				Array.prototype.forEach.call(elements, elem=>elem.parentNode.removeChild(elem));
			}
			(function clickDirectInput() {
				var elements = new Array(...document.getElementsByClassName("hint"));
				elements.forEach(e => e.addEventListener("mouseenter", hintEnter));
				elements.forEach(e => e.addEventListener("mouseleave", hintLeave));
			})();
		</script>
	</body>
</html>
