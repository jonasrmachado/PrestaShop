<?php
/*
* 2007-2011 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2011 PrestaShop SA
*  @version  Release: $Revision: 7307 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class AdminStockManagementControllerCore extends AdminController
{
	public function __construct()
	{
		$this->context = Context::getContext();
		$this->table = 'product';
		$this->className = 'Product';
		$this->lang = true;

		$this->fieldsDisplay = array(
			'reference' => array(
				'title' => $this->l('Reference'),
				'align' => 'center',
				'filter_key' => 'a!reference',
				'width' => 100,
				'widthColumn' => 150
			),
			'ean13' => array(
				'title' => $this->l('EAN13'),
				'align' => 'center',
				'filter_key' => 'a!ean13',
				'width' => 75,
				'widthColumn' => 100
			),
			'name' => array(
				'title' => $this->l('Name'),
				'width' => 350,
				'widthColumn' => 'auto',
			),
			'stock' => array(
				'title' => $this->l('Total quantities in stock'),
				'width' => 50,
				'widthColumn' => 60,
				'orderby' => false,
				'filter' => false,
				'search' => false,
			),
		);

		parent::__construct();

		// Override confirmation messages specifically for this controller
		$this->_conf = array(
			1 => $this->l('The product was successfully added to stock'),
			2 => $this->l('The product was properly removed from the stock'),
			3 => $this->l('The transfer was done properly'),
		);
	}

	/**
	 * AdminController::initList() override
	 * @see AdminController::initList()
	 */
	public function initList()
	{
		$this->addRowAction('details');
		$this->addRowAction('addstock');
		$this->addRowAction('removestock');
		$this->addRowAction('transferstock');

		//no link on list rows
		$this->list_no_link = true;

		$this->_select = 'a.id_product as id, COUNT(pa.id_product_attribute) as variations';
		$this->_join = 'LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa ON (pa.id_product = a.id_product)';

		$this->displayInformation(
			$this->l('This interface allows you to manage the stocks of each of your products and their variations.
				The quantities of each product are global, so includes all quantities of each warehouses.
				You can add and delete products related to a given warehouse.
				You can transfer products from a warehouse to another.'
			)
		);

		return parent::initList();
	}

	/**
	 * AdminController::initForm() override
	 * @see AdminController::initForm()
	 */
	public function initForm()
	{
		//get warehouses list
		$warehouses = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT `id_warehouse`, CONCAT(`reference`, " - ", `name`) as name
			FROM `'._DB_PREFIX_.'warehouse`
			ORDER BY `reference` ASC');

		$currencies = Currency::getCurrencies();

		switch ($this->display)
		{
			case 'addstock' :
				$this->fields_form = array(
					'legend' => array(
						'title' => $this->l('Add product to stock'),
						'image' => '../img/admin/arrow_up.png'
					),
					'input' => array(
						array(
							'type' => 'hidden',
							'name' => 'is_post',
						),
						array(
							'type' => 'hidden',
							'name' => 'id_product',
						),
						array(
							'type' => 'hidden',
							'name' => 'id_product_attribute',
						),
						array(
							'type' => 'hidden',
							'name' => 'check',
						),
						array(
							'type' => 'text',
							'label' => $this->l('Reference:'),
							'name' => 'reference',
							'size' => 30,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('Manufacturer reference:'),
							'name' => 'manufacturer_reference',
							'size' => 30,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('EAN13:'),
							'name' => 'ean13',
							'size' => 15,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('Name :'),
							'name' => 'name',
							'size' => 75,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('Quantity to add:'),
							'name' => 'quantity',
							'size' => 10,
							'maxlength' => 6,
							'required' => true,
							'p' => $this->l('Physical quantity to add to the stock for this product')
						),
						array(
							'type' => 'radio',
							'label' => $this->l('Usable for sale?:'),
							'name' => 'usable',
							'required' => true,
							'class' => 't',
							'is_bool' => true,
							'values' => array(
								array(
									'id' => 'active_on',
									'value' => 1,
									'label' => $this->l('Enabled')
								),
								array(
									'id' => 'active_off',
									'value' => 0,
									'label' => $this->l('Disabled')
								)
							),
							'p' => $this->l('Is this quantity is usable for sale on shops, or reserved in the warehouse for other purpose ?:')
						),
						array(
							'type' => 'select',
							'label' => $this->l('Warehouse:'),
							'name' => 'id_warehouse',
							'required' => true,
							'options' => array(
								'query' => $warehouses,
								'id' => 'id_warehouse',
								'name' => 'name'
							),
							'p' => $this->l('Select the warehouse where you want to add the product into')
						),
						array(
							'type' => 'text',
							'label' => $this->l('Unit price (TE):'),
							'name' => 'price',
							'required' => true,
							'size' => 10,
							'maxlength' => 10,
							'p' => $this->l('Unit purchase price or unit manufacturing cost for this product, tax excluded')
						),
						array(
							'type' => 'select',
							'label' => $this->l('Currency:'),
							'name' => 'id_currency',
							'required' => true,
							'options' => array(
								'query' => $currencies,
								'id' => 'id_currency',
								'name' => 'name'
							),
							'p' => $this->l('The currency associated to the product unit price.'),
						),
						array(
							'type' => 'select',
							'label' => $this->l('Reason :'),
							'name' => 'id_stock_mvt_reason',
							'required' => true,
							'options' => array(
								'query' => StockMvtReason::getStockMvtReasons($this->context->language->id, 1),
								'id' => 'id_stock_mvt_reason',
								'name' => 'name'
							),
							'p' => $this->l('Reason to add in stock movements'),
						),
					),
					'submit' => array(
						'title' => $this->l('   Add to stock   '),
						'class' => 'button'
					)
				);
			break;

			case 'removestock' :
				$this->fields_form = array(
					'legend' => array(
						'title' => $this->l('Remove product from stock'),
						'image' => '../img/admin/arrow_down.png'
					),
					'input' => array(
						array(
							'type' => 'hidden',
							'name' => 'is_post',
						),
						array(
							'type' => 'hidden',
							'name' => 'id_product',
						),
						array(
							'type' => 'hidden',
							'name' => 'id_product_attribute',
						),
						array(
							'type' => 'hidden',
							'name' => 'check',
						),
						array(
							'type' => 'text',
							'label' => $this->l('Reference:'),
							'name' => 'reference',
							'size' => 30,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('Manufacturer reference:'),
							'name' => 'manufacturer_reference',
							'size' => 30,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('EAN13:'),
							'name' => 'ean13',
							'size' => 15,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('Name :'),
							'name' => 'name',
							'size' => 75,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('Quantity to remove:'),
							'name' => 'quantity',
							'size' => 10,
							'maxlength' => 6,
							'required' => true,
							'p' => $this->l('Physical quantity to remove from the stock for this product')
						),
						array(
							'type' => 'radio',
							'label' => $this->l('Usable for sale?:'),
							'name' => 'usable',
							'required' => true,
							'class' => 't',
							'is_bool' => true,
							'values' => array(
								array(
									'id' => 'active_on',
									'value' => 1,
									'label' => $this->l('Enabled')
								),
								array(
									'id' => 'active_off',
									'value' => 0,
									'label' => $this->l('Disabled')
								)
							),
							'p' => $this->l('Do you want to remove this quantity from usable quantity for sale on shops ?:')
						),
						array(
							'type' => 'select',
							'label' => $this->l('Warehouse:'),
							'name' => 'id_warehouse',
							'required' => true,
							'options' => array(
								'query' => $warehouses,
								'id' => 'id_warehouse',
								'name' => 'name'
							),
							'p' => $this->l('Select the warehouse from where you want to remove the product')
						),
						array(
							'type' => 'select',
							'label' => $this->l('Reason :'),
							'name' => 'id_stock_mvt_reason',
							'required' => true,
							'options' => array(
								'query' => StockMvtReason::getStockMvtReasons($this->context->language->id, -1),
								'id' => 'id_stock_mvt_reason',
								'name' => 'name'
							),
							'p' => $this->l('Reason to add in stock movements'),
						),
					),
					'submit' => array(
						'title' => $this->l('   Remove from stock   '),
						'class' => 'button'
					)
				);
			break;

			case 'transferstock' :
				$this->fields_form = array(
					'legend' => array(
						'title' => $this->l('Transfert product from warehouse to another'),
						'image' => '../img/admin/arrow-right.png'
					),
					'input' => array(
						array(
							'type' => 'hidden',
							'name' => 'is_post',
						),
						array(
							'type' => 'hidden',
							'name' => 'id_product',
						),
						array(
							'type' => 'hidden',
							'name' => 'id_product_attribute',
						),
						array(
							'type' => 'hidden',
							'name' => 'check',
						),
						array(
							'type' => 'text',
							'label' => $this->l('Reference:'),
							'name' => 'reference',
							'size' => 30,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('Manufacturer reference:'),
							'name' => 'manufacturer_reference',
							'size' => 30,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('EAN13:'),
							'name' => 'ean13',
							'size' => 15,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('Name :'),
							'name' => 'name',
							'size' => 75,
							'disabled' => true,
						),
						array(
							'type' => 'text',
							'label' => $this->l('Quantity to transfer:'),
							'name' => 'quantity',
							'size' => 10,
							'maxlength' => 6,
							'required' => true,
							'p' => $this->l('Physical quantity to transfer for this product')
						),
						array(
							'type' => 'select',
							'label' => $this->l('Source Warehouse:'),
							'name' => 'id_warehouse_from',
							'required' => true,
							'options' => array(
								'query' => $warehouses,
								'id' => 'id_warehouse',
								'name' => 'name'
							),
							'p' => $this->l('Select the warehouse from where you want to transfer the product')
						),
						array(
							'type' => 'radio',
							'label' => $this->l('Usable for sale in source warehouse ?:'),
							'name' => 'usable_from',
							'required' => true,
							'class' => 't',
							'is_bool' => true,
							'values' => array(
								array(
									'id' => 'active_on',
									'value' => 1,
									'label' => $this->l('Enabled')
								),
								array(
									'id' => 'active_off',
									'value' => 0,
									'label' => $this->l('Disabled')
								)
							),
							'p' => $this->l('Do you want to transfer this quantity from usable quantity for sale on shops in the source warehouse ?:')
						),
						array(
							'type' => 'select',
							'label' => $this->l('Destination Warehouse:'),
							'name' => 'id_warehouse_to',
							'required' => true,
							'options' => array(
								'query' => $warehouses,
								'id' => 'id_warehouse',
								'name' => 'name'
							),
							'p' => $this->l('Select the warehouse where you want to transfer the product')
						),
						array(
							'type' => 'radio',
							'label' => $this->l('Usable for sale in destination warehosue ?:'),
							'name' => 'usable_to',
							'required' => true,
							'class' => 't',
							'is_bool' => true,
							'values' => array(
								array(
									'id' => 'active_on',
									'value' => 1,
									'label' => $this->l('Enabled')
								),
								array(
									'id' => 'active_off',
									'value' => 0,
									'label' => $this->l('Disabled')
								)
							),
							'p' => $this->l('Do you want to transfer this quantity as usable quantity for sale on shops in the destination warehouse ?:')
						),
					),
					'submit' => array(
						'title' => $this->l('   Transfer   '),
						'class' => 'button'
					)
				);
			break;
		}
	}

	/**
	 * AdminController::postProcess() override
	 * @see AdminController::postProcess()
	 */
	public function postProcess()
	{
		parent::postProcess();

		// Global checks when add / remove / transfer product
		if ((Tools::isSubmit('addstock') || Tools::isSubmit('removestock') || Tools::isSubmit('transferstock') ) && Tools::isSubmit('is_post'))
		{
			// get product ID
			$id_product = (int)Tools::getValue('id_product', 0);
			if ($id_product <= 0)
				$this->_errors[] = Tools::displayError('The selected product is not valid.');

			// get product_attribute ID
			$id_product_attribute = (int)Tools::getValue('id_product_attribute', 0);

			// check the product hash
			$check = Tools::getValue('check', '');
			$check_valid = md5(_COOKIE_KEY_.$id_product.$id_product_attribute);
			if ($check != $check_valid)
				$this->_errors[] = Tools::displayError('The selected product is not valid.');

			// get quantity and check that the post value is really an integer
			// If it's not, we have to do nothing.
			$quantity = Tools::getValue('quantity', 0);
			if (!is_numeric($quantity) || (int)$quantity <= 0)
				$this->_errors[] = Tools::displayError('The quantity value is not valid.');
			$quantity = (int)$quantity;

			$token = Tools::getValue('token') ? Tools::getValue('token') : $this->token;
			$redirect = self::$currentIndex.'&token='.$token;
		}

		// Global checks when add / remove product
		if ((Tools::isSubmit('addstock') || Tools::isSubmit('removestock') ) && Tools::isSubmit('is_post'))
		{
			// get warehouse id
			$id_warehouse = (int)Tools::getValue('id_warehouse', 0);
			if ($id_warehouse <= 0 || !Warehouse::exists($id_warehouse))
				$this->_errors[] = Tools::displayError('The selected warehouse is not valid.');

			// get stock movement reason id
			$id_stock_mvt_reason = (int)Tools::getValue('id_stock_mvt_reason', 0);
			if ($id_stock_mvt_reason <= 0 || !StockMvtReason::exists($id_stock_mvt_reason))
				$this->_errors[] = Tools::displayError('The reason is not valid.');

			// get usable flag
			$usable = Tools::getValue('usable', null);
			if (is_null($usable))
				$this->_errors[] = Tools::displayError('You have to specify if the product quantity is usable for sale on shops.');
			$usable = (bool)$usable;
		}

		if (Tools::isSubmit('addstock') && Tools::isSubmit('is_post'))
		{
			// get product unit price
			$price = str_replace(',', '.', Tools::getValue('price', 0));
			if (!is_numeric($price))
				$this->_errors[] = Tools::displayError('The product price is not valid.');
			$price = round(floatval($price), 6);

			// get product unit price currency id
			$id_currency = (int)Tools::getValue('id_currency', 0);
			if ($id_currency <= 0 || ( !($result = Currency::getCurrency($id_currency)) || empty($result) ))
				$this->_errors[] = Tools::displayError('The selected currency is not valid.');

			// if all is ok, add stock
			if (count($this->_errors) == 0)
			{
				$warehouse = new Warehouse($id_warehouse);

				// convert price to warehouse currency if needed
				if ($id_currency != $warehouse->id_currency)
				{
					// First convert price to the default currency
					$price_converted_to_default_currency = Tools::convertPrice($price, $id_currency, false);

					// Convert the new price from default currency to needed currency
					$price = Tools::convertPrice($price_converted_to_default_currency, $warehouse->id_currency, true);
				}

				// add stock
				$stock_manager = StockManagerFactory::getManager();

				if ($stock_manager->addProduct($id_product, $id_product_attribute, $warehouse, $quantity, $id_stock_mvt_reason, $price, $usable))
					Tools::redirectAdmin($redirect.'&conf=1');
				else
					$this->_errors[] = Tools::displayError('An error occured. No stock was added.');
			}
		}

		if (Tools::isSubmit('removestock') && Tools::isSubmit('is_post'))
		{
			// if all is ok, remove stock
			if (count($this->_errors) == 0)
			{
				$warehouse = new Warehouse($id_warehouse);

				// remove stock
				$stock_manager = StockManagerFactory::getManager();
				$removed_products = $stock_manager->removeProduct($id_product, $id_product_attribute, $warehouse, $quantity, $id_stock_mvt_reason, $usable);

				if (count($removed_products) > 0)
					Tools::redirectAdmin($redirect.'&conf=2');
				else
					$this->_errors[] = Tools::displayError('It is not possible to remove the specified quantity or an error occured. No stock was removed.');
			}
		}

		if (Tools::isSubmit('transferstock') && Tools::isSubmit('is_post'))
		{
			// get source warehouse id
			$id_warehouse_from = (int)Tools::getValue('id_warehouse_from', 0);
			if ($id_warehouse_from <= 0 || !Warehouse::exists($id_warehouse_from))
				$this->_errors[] = Tools::displayError('The source warehouse is not valid.');

			// get destination warehouse id
			$id_warehouse_to = (int)Tools::getValue('id_warehouse_to', 0);
			if ($id_warehouse_to <= 0 || !Warehouse::exists($id_warehouse_to))
				$this->_errors[] = Tools::displayError('The destination warehouse is not valid.');

			// get usable flag for source warehouse
			$usable_from = Tools::getValue('usable_from', null);
			if (is_null($usable_from))
				$this->_errors[] = Tools::displayError('You have to specify if the product quantity is usable for sale on shops in source warehouse.');
			$usable_from = (bool)$usable_from;

			// get usable flag for destination warehouse
			$usable_to = Tools::getValue('usable_to', null);
			if (is_null($usable_to))
				$this->_errors[] = Tools::displayError('You have to specify if the product quantity is usable for sale on shops in destination warehouse.');
			$usable_to = (bool)$usable_to;

			// if all is ok, transfer stock
			if (count($this->_errors) == 0)
			{
				// transfer stock
				$stock_manager = StockManagerFactory::getManager();

				$is_transfer = $stock_manager->transferBetweenWarehouses(
					$id_product,
					$id_product_attribute,
					$quantity,
					$id_warehouse_from,
					$id_warehouse_to,
					$usable_from,
					$usable_to
				);

				if ($is_transfer)
					Tools::redirectAdmin($redirect.'&conf=3');
				else
					$this->_errors[] = Tools::displayError('It is not possible to transfer the specified quantity, or an error occured. No stock was transfered.');
			}
		}
	}

	/**
	 * AdminController::init() override
	 * @see AdminController::init()
	 */
	public function init()
	{
		parent::init();

		if (Tools::isSubmit('addstock'))
			$this->display = 'addstock';

		if (Tools::isSubmit('removestock'))
			$this->display = 'removestock';

		if (Tools::isSubmit('transferstock'))
			$this->display = 'transferstock';
	}

	/**
	 * method call when ajax request is made with the details row action
	 * @see AdminController::postProcess()
	 */
	public function ajaxProcess()
	{
		// test if an id is submit
		if (Tools::isSubmit('id'))
		{
			// override attributes
			$this->identifier = 'id_product_attribute';
			$this->display = 'list';
			$this->lang = false;

			$this->addRowAction('addstock');
			$this->addRowAction('removestock');
			$this->addRowAction('transferstock');

			// get current lang id
			$lang_id = (int)$this->context->language->id;

			// Get product id
			$product_id = (int)Tools::getValue('id');

			// Load product attributes with sql override
			$this->table = 'product_attribute';

			$this->_select = 'a.id_product_attribute as id, a.id_product, a.reference, a.ean13,
				IFNULL(CONCAT(pl.name, \' : \', GROUP_CONCAT(agl.`name`, \' - \', al.name SEPARATOR \', \')),pl.name) as name';

			$this->_join = '
				INNER JOIN '._DB_PREFIX_.'product_lang pl ON (pl.id_product = a.id_product AND pl.id_lang = '.$lang_id.')
				LEFT JOIN '._DB_PREFIX_.'product_attribute_combination pac ON (pac.id_product_attribute = a.id_product_attribute)
				LEFT JOIN '._DB_PREFIX_.'attribute atr ON (atr.id_attribute = pac.id_attribute)
				LEFT JOIN '._DB_PREFIX_.'attribute_lang al ON (al.id_attribute = atr.id_attribute AND al.id_lang = '.$lang_id.')
				LEFT JOIN '._DB_PREFIX_.'attribute_group_lang agl ON (agl.id_attribute_group = atr.id_attribute_group AND agl.id_lang = '.$lang_id.')';

			$this->_where = 'AND a.id_product = '.$product_id;
			$this->_group = 'GROUP BY a.id_product_attribute';

			// get list and force no limit clause in the request
			$this->getList($this->context->language->id, null, null, 0, false);

			// Render list
			$helper = new HelperList();
			$helper->actions = $this->actions;
			$helper->list_skip_actions = $this->list_skip_actions;
			$helper->no_link = true;
			$helper->shopLinkType = '';
			$helper->identifier = $this->identifier;
			// Force render - no filter, form, js, sorting ...
			$helper->simple_header = true;
			$content = $helper->generateList($this->_list, $this->fieldsDisplay);

			echo Tools::jsonEncode(array('use_parent_structure' => false, 'data' => $content));
		}

		die;
	}

	/**
	 * AdminController::getList() override
	 * @see AdminController::getList()
	 */
	public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
	{
		parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);

		if ($this->display == 'list')
		{
			// Check each row to see if there are combinations and get the correct action in consequence
			$nb_items = count($this->_list);

			for ($i = 0; $i < $nb_items; $i++)
			{
				$item = &$this->_list[$i];

				// if it's an ajax request we have to consider manipulating a product variation
				if ($this->ajax == '1')
				{
					// no details for this row
					$this->addRowActionSkipList('details', array($item['id']));

					// specify actions in function of stock
					$this->skipActionByStock($item, true);
				}
				// If current product has variations
				else if ((int)$item['variations'] > 0)
				{
					// we have to desactivate stock actions on current row
					$this->addRowActionSkipList('addstock', array($item['id']));
					$this->addRowActionSkipList('removestock', array($item['id']));
					$this->addRowActionSkipList('transferstock', array($item['id']));
				}
				else
				{
					//there are no variations of current product, so we don't want to show details action
					$this->addRowActionSkipList('details', array($item['id']));

					// specify actions in function of stock
					$this->skipActionByStock($item, false);
				}
			}
		}
	}

	/**
	 * Check stock for a given product or product attribute
	 * and manage available actions in consequence
	 *
	 * @param array $item reference to the current item
	 * @param bool $is_product_attribute specify if it's a product or a product variation
	 */
	private function skipActionByStock(&$item, $is_product_variation = false)
	{
		$stock_manager = StockManagerFactory::getManager();

		//get stocks for this product
		if ($is_product_variation)
			$stock = $stock_manager->getProductPhysicalQuantities($item['id_product'], $item['id']);
		else
			$stock = $stock_manager->getProductPhysicalQuantities($item['id'], 0);

		//affects stock to the list for display
		$item['stock'] = $stock;

		if ($stock <= 0)
		{
			//there is no stock, we can only add stock
			$this->addRowActionSkipList('removestock', array($item['id']));
			$this->addRowActionSkipList('transferstock', array($item['id']));
		}
	}

	/**
	 * AdminController::initContent() override
	 * @see AdminController::initContent()
	 */
	public function initContent()
	{
		// Manage the add stock form
		if ($this->display == 'addstock' || $this->display == 'removestock' || $this->display == 'transferstock')
		{
			if (Tools::isSubmit('id_product') || Tools::isSubmit('id_product_attribute'))
			{
				// get id product and product attribute if possible
				$id_product = (int)Tools::getValue('id_product', 0);
				$id_product_attribute = (int)Tools::getValue('id_product_attribute', 0);

				$product_is_valid = false;
				$lang_id = $this->context->language->id;

				// try to load product attribute first
				if ($id_product_attribute > 0)
				{
					// try to load product attribute
					$combination = new Combination($id_product_attribute);
					if (is_int($combination->id))
					{
						$product_is_valid = true;
						$id_product = $combination->id_product;
						$reference = $combination->reference;
						$ean13 = $combination->ean13;
						$manufacturer_reference = $combination->supplier_reference;

						// get the full name for this combination
						$query = new DbQuery();

						$query->select('IFNULL(CONCAT(pl.`name`, \' : \', GROUP_CONCAT(agl.`name`, \' - \', al.`name` SEPARATOR \', \')),pl.`name`) as name');
						$query->from('product_attribute a');
						$query->join('INNER JOIN '._DB_PREFIX_.'product_lang pl ON (pl.`id_product` = a.`id_product` AND pl.`id_lang` = '.$lang_id.')
							LEFT JOIN '._DB_PREFIX_.'product_attribute_combination pac ON (pac.`id_product_attribute` = a.`id_product_attribute`)
							LEFT JOIN '._DB_PREFIX_.'attribute atr ON (atr.`id_attribute` = pac.`id_attribute`)
							LEFT JOIN '._DB_PREFIX_.'attribute_lang al ON (al.`id_attribute` = atr.`id_attribute` AND al.`id_lang` = '.$lang_id.')
							LEFT JOIN '._DB_PREFIX_.'attribute_group_lang agl ON (agl.`id_attribute_group` = atr.`id_attribute_group` AND agl.`id_lang` = '.$lang_id.')'
						);
						$query->where('a.`id_product_attribute` = '.$id_product_attribute);
						$name = Db::getInstance()->getValue($query);
					}
				}
				// try to load a simple product
				else
				{
					$product = new Product($id_product, false, $lang_id);
					if (is_int($product->id))
					{
						$product_is_valid = true;
						$reference = $product->reference;
						$ean13 = $product->ean13;
						$name = $product->name;
						$manufacturer_reference = $product->supplier_reference;
					}
				}

				if ($product_is_valid === true)
				{
					// init form
					$this->initForm();
					$this->getlanguages();

					$helper = new HelperForm();

					// Check if form template has been overriden
					if (file_exists($this->context->smarty->template_dir.'/'.$this->tpl_folder.'form.tpl'))
						$helper->tpl = $this->tpl_folder.'form.tpl';

					$helper->submit_action = $this->display;
					$helper->currentIndex = self::$currentIndex;
					$helper->token = $this->token;
					$helper->id = null; // no display standard hidden field in the form
					$helper->languages = $this->_languages;
					$helper->default_form_language = $this->default_form_language;
					$helper->allow_employee_form_lang = $this->allow_employee_form_lang;

					$helper->fields_value = array(
						'id_product' => $id_product,
						'id_product_attribute' => $id_product_attribute,
						'reference' => $reference,
						'manufacturer_reference' => $manufacturer_reference,
						'name' => $name,
						'ean13' => $ean13,
						'check' => md5(_COOKIE_KEY_.$id_product.$id_product_attribute),
						'quantity' => Tools::getValue('quantity', ''),
						'id_warehouse' => Tools::getValue('id_warehouse', ''),
						'usable' => Tools::getValue('usable', ''),
						'price' => Tools::getValue('price', ''),
						'id_currency' => Tools::getValue('id_currency', ''),
						'id_stock_mvt_reason' => Tools::getValue('id_stock_mvt_reason', ''),
						'is_post' => 1,
					);

					if ($this->display == 'transferstock')
					{
						$helper->fields_value['id_warehouse_from'] = Tools::getValue('id_warehouse_from', '');
						$helper->fields_value['id_warehouse_to'] = Tools::getValue('id_warehouse_to', '');
						$helper->fields_value['usable_from'] = Tools::getValue('usable_from', '');
						$helper->fields_value['usable_to'] = Tools::getValue('usable_to', '');
					}

					$this->content .= $helper->generateForm($this->fields_form);

					$this->context->smarty->assign(array(
						'content' => $this->content
					));
				}
				else
					$this->_errors[] = Tools::displayError('The specified product is not valid');
			}
		}
		else
		{
			$this->display = 'list';
			parent::initContent();
		}
	}

	 /**
	 * Display addstock action link
	 * @param string $token the token to add to the link
	 * @param int $id the identifier to add to the link
	 * @return string
	 */
	public function displayAddstockLink($token = null, $id)
	{
        if (!array_key_exists('AddStock', self::$cache_lang))
            self::$cache_lang['AddStock'] = $this->l('Add stock');

        $this->context->smarty->assign(array(
            'href' => self::$currentIndex.
            	'&'.$this->identifier.'='.$id.
            	'&addstock&token='.($token != null ? $token : $this->token),
            'action' => self::$cache_lang['AddStock'],
        ));

        return $this->context->smarty->fetch(_PS_ADMIN_DIR_.'/themes/template/list_action_addstock.tpl');
	}

    /**
	 * Display removestock action link
	 * @param string $token the token to add to the link
	 * @param int $id the identifier to add to the link
	 * @return string
	 */
    public function displayRemovestockLink($token = null, $id)
    {
        if (!array_key_exists('RemoveStock', self::$cache_lang))
            self::$cache_lang['RemoveStock'] = $this->l('Remove stock');

        $this->context->smarty->assign(array(
            'href' => self::$currentIndex.
            	'&'.$this->identifier.'='.$id.
            	'&removestock&token='.($token != null ? $token : $this->token),
            'action' => self::$cache_lang['RemoveStock'],
        ));

        return $this->context->smarty->fetch(_PS_ADMIN_DIR_.'/themes/template/list_action_removestock.tpl');
    }

    /**
	 * Display transferstock action link
	 * @param string $token the token to add to the link
	 * @param int $id the identifier to add to the link
	 * @return string
	 */
    public function displayTransferstockLink($token = null, $id)
    {
        if (!array_key_exists('TransferStock', self::$cache_lang))
            self::$cache_lang['TransferStock'] = $this->l('Transfer stock');

        $this->context->smarty->assign(array(
            'href' => self::$currentIndex.
            	'&'.$this->identifier.'='.$id.
            	'&transferstock&token='.($token != null ? $token : $this->token),
            'action' => self::$cache_lang['TransferStock'],
        ));

        return $this->context->smarty->fetch(_PS_ADMIN_DIR_.'/themes/template/list_action_transferstock.tpl');
    }
}