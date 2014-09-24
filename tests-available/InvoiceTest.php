<?php

namespace PrestaShop\FunctionalTest;

class InvoiceTest extends \PrestaShop\TestCase\TestCase
{
	public function getScenarios()
	{
		$scenarios = [];
		$src_dir = __DIR__.'/data/InvoiceTest';
		foreach (scandir($src_dir) as $entry)
			if (preg_match('/\.json$/', $entry))
				$scenarios[] = ["$src_dir/$entry"];

		return $scenarios;
	}

	/**
	 * @dataProvider getScenarios
	 * @parallelize 4
	 */
	public function testInvoice($scenario_file)
	{
		$shop = static::getShop();
		$browser = $shop->getBrowser();

		$shop->getBackOfficeNavigator()->login();

		$scenario = json_decode(file_get_contents($scenario_file), true);

		if (isset($scenario['meta']['rounding_mode']))
			$shop->getPreferencesManager()->setRoundingMode($scenario['meta']['rounding_mode']);

		if (isset($scenario['meta']['rounding_type']))
			$shop->getPreferencesManager()->setRoundingType($scenario['meta']['rounding_type']);

		$shop->getCarrierManager()->createCarrier($scenario['carrier']);

		if (isset($scenario['discounts']))
		{
			foreach ($scenario['discounts'] as $name => $discount)
				$shop->getCartRulesManager()->createCartRule(['name' => $name, 'discount' => $discount]);
		}

		foreach ($scenario['products'] as $name => $data)
		{
			$tax_rules_group = 0;

			if (isset($data['vat']))
			{
				$vat = $data['vat'];

				// create the tax rules group but skip tax rules group checks, since they're awful slow, and
				// tax rules groups creation is already tested in a dedicated test case
				$tax_rules_group = $shop->getTaxManager()->getOrCreateTaxRulesGroupFromString($vat, true);
			}

			$data['tax_rules_group'] = $tax_rules_group;
			$data['name'] = $name;

			$scenario['products'][$name]['info'] = $shop->getProductManager()->createProduct($data);
		}

		$shop->getFrontOfficeNavigator()->login();

		foreach ($scenario['products'] as $name => $data)
		{
			$shop
			->getPageObject('FrontOfficeProductSheet')
			->visit($data['info']['fo_url'])
			->setQuantity($data['quantity'])
			->addToCart();
		}

		$data = $shop->getCheckoutManager()->orderCurrentCartFiveSteps([
			'carrier' => $scenario['carrier']['name'],
			'payment' => 'bankwire'
		]);

		$cart_total = $data['cart_total'];
		$id_order = $data['id_order'];

		$json = $shop->getOrderManager()->visit($id_order)->validate()->getInvoiceAsJSON();

		$total_mapping = [
			'to_pay_tax_included' => 'total_paid_tax_incl',
			'to_pay_tax_excluded' => 'total_paid_tax_excl',
			'products_tax_included' => 'total_products_wt',
			'products_tax_excluded' => 'total_products',
			'shipping_tax_included' => 'total_shipping_tax_incl',
			'shipping_tax_excluded' => 'total_shipping_tax_excl',
			'discounts_tax_included' => 'total_discounts_tax_incl',
			'discounts_tax_excluded' => 'total_discounts_tax_excl',
			'wrapping_tax_included' => 'total_wrapping_tax_incl',
			'wrapping_tax_excluded' => 'total_wrapping_tax_excl'
		];

		$errors = [];

		if ($cart_total != $json['order']['total_paid_tax_incl'])
		{
			$errors[] = "Cart total `$cart_total` differs from invoice total of `{$json['order']['total_paid_tax_incl']}`.";
		}

		if (isset($scenario['expect']['invoice']['total']))
		{
			foreach ($scenario['expect']['invoice']['total'] as $e_key => $e_value)
			{
				$a_value = (float)$json['order'][$total_mapping[$e_key]];
				if((float)$e_value !== $a_value)
					$errors[] = "Got `$a_value` instead of `$e_value` for `$e_key`.";
			}
		}

		if (isset($scenario['expect']['invoice']['tax']['products']))
		{	
			$a = [];

			foreach ($json['tax_tab']['product_tax_breakdown'] as $a_rate => $a_amount)
				$a[(float)$a_rate] = (float)$a_amount['total_amount'];
		
			foreach ($scenario['expect']['invoice']['tax']['products'] as $e_rate => $e_amount)
			{
				$e_rate = (float)$e_rate;
				$e_amount = (float)$e_amount;

				if (!isset($a[$e_rate]) || $a[$e_rate] !== $e_amount)
				{
					$a_amount = isset($a[$e_rate]) ? $a[$e_rate] : null;
					$errors[] = "Invalid actual tax amount for rate `$e_rate`: got `$a_amount` instead of `$e_amount`.";
				}
			}
		}

		if (!empty($errors))
			throw new \PrestaShop\Exception\InvoiceIncorrectException(implode("\n", $errors));
	}
}