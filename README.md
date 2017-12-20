# magento-tools

Tools that I created to help managing Magento applications

### Category Manager

A tool to easen the process of importing/exporting categories, associating products with categories and other similar tasks that can be difficult to do via the normal magento importing/exporting module.

#### Why should you use this tool?

Magento admin is generally slow and difficult to use efficiently, putting categories on products always results in errors and other problems, not to mention how Magento's native export module gives out a lot of unnecessary columns that have to be removed manually for sanity, this tool gives out tables with 2 or 3 columns, all within 1 button of distance. This script aims to be dead simple and stand-alone: Put in your application, open, export, edit, import, delete the tool, done.

Tested with Magento 1.7.x ~ 1.9.x

![Category Manager English Version](https://raw.githubusercontent.com/GuilhermeRossato/magento-tools/master/category_manager_image.png)

#### Instalation

**Warning**: this opens up a lot of **vulnerability** to your magento aplication if left easily accessible, the optional step below makes the use of this tool safer to use but you should remove the script after using it. Anyone can just follow that link and alter products/categories on your website without the need for login.

1. Download the category_manager.php file
2. Open your ftp and navigate to the folder with your Magento application (there's things like app/, skin/, index.php)
3. (*optional*) Create a new folder and name it something like "utilities" or "tools"
4. Put the category_manager.php file in that folder
5. Open in your browser http://yourwebsite.com.br/your_folder_if_exists/category_manager.php (adapting the link however necessary)

#### Usage

Normal usage is exporting a list of products with categories, putting the categories in the "category" columns, then going back to the tool and importing it. That middle-step is usually done by the shop manager.
