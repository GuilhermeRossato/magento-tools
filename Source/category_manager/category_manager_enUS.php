<?php
$exporta1 = "List all products with their categories";
$exporta2 = "List all categories without roots";
$exporta3 = "List all categories with roots";
$exporta4 = "List products with their prices";
$importa1 = "Associate products with categories";
$importa2 = "Associate categories skipping errors";
$importa3 = "Associate cats, creating if necessary";
$importa4 = "Associate products with prices";
$importa5 = "Associate prices skipping errors";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
	function getMagentoPath() {
		$tests = [".", "..", "../..", "public_html", "../public_html", "web", "../web"];
		foreach ($tests as $path)
			if (is_dir($path) && file_exists($path."/index.php") && is_dir($path."/app/") && is_dir($path."/var/") && is_dir($path."/media/") && is_dir($path."/skin/"))
				return $path;
	}
	if (!($magentoPath = getMagentoPath())) {
		echo "Error: Could not find Magento installation path";
		die();
	}
	require_once implode(DIRECTORY_SEPARATOR, [$magentoPath, "app", "Mage.php"]);
	Mage::setIsDeveloperMode(true);
	umask(0);
	Mage::app('admin');
	Mage::register('isSecureArea', 1);
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	if ($_POST['submit'] == $importa1) {
		echo gmdate('d m Y');
	} else if ($_POST['submit'] == $exporta1 || $_POST['submit'] == $exporta4) {
		function get_sku_list() {
			global $exporta4;
			$products = Mage::getModel('catalog/product')->getCollection();
			$products->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns('*');
			$result = [];
			foreach ($products as $product) {
				if ($_POST['submit'] == $exporta4) {
					array_push($result, [$product->getSku(),$product->getData('qty'),$product->getSpecialPrice()]);
				} else {
					array_push($result, [$product->getSku(),implode(",",$product->getCategoryIds())]);
				}
			}
			return $result;
		}
		if ($_POST['submit'] == $exporta4) {
			$ret = "sku,price,special_price\n";
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
						<input type="submit" name="submit" value="<?php echo $exporta1 ?>">
						<input type="submit" name="submit" value="<?php echo $exporta4 ?>">
					</a>
				</div>
				<div class="half">
					<a class="pad">
						<h2>Import</h2>
						<input type="file" name="arquivo">
						<input type="submit" class="hinted" name="submit" value="<?php echo $importa1; ?>">
						<span class="hint">Send a .csv with skus and category id to associate, file header should be:<br>sku,category<br>If a product has multiple categories, they must be separated by a comma.</span>
						<input type="submit" name="submit" value="<?php echo $importa2; ?>">
						<input type="submit" name="submit" value="<?php echo $importa3; ?>">
						<input type="submit" class="hinted" name="submit" value="<?php echo $importa4; ?>">
						<span class="hint">Send a .csv with skus and prices to be associated, header should be:<br>sku,price or <br>sku,price,promotional_price</span>
						<input type="submit" name="submit" value="<?php echo $importa5; ?>">
					</a>
				</div>
				<div class="full"><a class="pad" id="result-errors"></a></div>
				<div class="credits">This tool was developed by Guilherme Rossato for Magento 1.9.2 (20/12/2017)</div>
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
