<?php

/**
 * SveaWebPay Payment Module for Magento.
 *   Copyright (C) 2012  SveaWebPay
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  Any questions may be directed to kundtjanst.sveawebpay@sveaekonomi.se
 */
 
class SveaWebPay_Common_Helper_Calculations extends Mage_Core_Helper_Abstract
{
    private $_mCurrentOrderIdProcessing = -1;
    
    private $_mCurrencyRate = null;
    
    private $_mCurrencyCode = null;
    private $_mBaseCurrencyCode = null;
    private $_mPaymentmethodCurrencyCode = null;
    
    private function isCurrencyCodesLoaded()
    {
        if(!$this->_mCurrencyCode || !$this->_mBaseCurrencyCode || !$this->_mPaymentmethodCurrencyCode)
            return false;
        return true;
    }
    
    public function convertFromBaseToPaymentmethodCurrency( $price )
    {
        $targetCurrency = Mage::getModel('directory/currency')->load( $this->_mPaymentmethodCurrencyCode,2 );
        return Mage::helper('directory')->currencyConvert($price, $this->_mBaseCurrencyCode,$targetCurrency );
    }
    
    private function convertFromBaseToDisplayCurrency( $price )
    {
        $targetCurrency = Mage::getModel('directory/currency')->load( $this->_mCurrencyCode,2 );
        return Mage::helper('directory')->currencyConvert($price, $this->_mBaseCurrencyCode,$targetCurrency );
    }
    
    private function convertFromDisplayToPaymentmethodCurrency( $price )
    {
        $targetCurrency = Mage::getModel('directory/currency')->load( $this->_mPaymentmethodCurrencyCode,2 );
        return Mage::helper('directory')->currencyConvert($price, $this->_mCurrencyCode,$targetCurrency );
    }
    
    private function convertFromDisplayToBaseCurrency( $price )
    {
        $targetCurrency = Mage::getModel('directory/currency')->load( $this->_mBaseCurrencyCode,2 );
        return Mage::helper('directory')->currencyConvert($price, $this->_mCurrencyCode,$targetCurrency );
    }  
    
    private function convertFromPaymentmethodToBaseCurrency( $price )
    {
        $targetCurrency = Mage::getModel('directory/currency')->load( $this->_mBaseCurrencyCode,2 );
        return Mage::helper('directory')->currencyConvert($price,$this->_mPaymentmethodCurrencyCode, $targetCurrency );
    }
    
    public function convertFromPaymentmethodToDisplayCurrency( $price )
    {
        $targetCurrency = Mage::getModel('directory/currency')->load( $this->_mCurrencyCode,2 );
        return Mage::helper('directory')->currencyConvert($price,$this->_mPaymentmethodCurrencyCode,$targetCurrency );
    }
    
    public function getBaseCurrencyCode()
    {
        return $this->_mBaseCurrencyCode;  
    }
    
    public function getBaseToCurrentCurrencyRate()
    {
        try
        {
            $baseCurrency = Mage::app()->getStore()->getBaseCurrencyCode();
            $currentCurrency = Mage::app()->getStore()->getCurrentCurrencyCode();
            
            if ($baseCurrency != $currentCurrency)
            {
                $currencyModel = Mage::getModel('directory/currency');
                $currencyModel->load($baseCurrency);
                return $currencyModel->getRate($currentCurrency);
            }
            return 1.0;
        }
        catch(Exception $exception)
        {
            $log = Mage::helper("swpcommon/log");
            $log->exception("Exception caught while getting currency from base to current currency. Exception given: " . $exception->getMessage());
        }
        
        return 1.0;
    }
    
    public function loadCurrencyCodes( $object,$method,$targetCurrencyCode = null )
    {
        $currencyCode = null;
        $currencyBaseCode = null;
        $baseCurrencyCode = null;
        $paymentmethodCurrencyCode = null;
        
        if(!$object)
            return false;
        
        if( $object instanceof Mage_Sales_Model_Order )
        {
            $currencyCode = $object->getOrderCurrencyCode();
            $baseCurrencyCode = $object->getOrderBaseCurrencyCode();
            $paymentmethodCurrencyCode = ($targetCurrencyCode == null) ? $baseCurrencyCode : $targetCurrencyCode;//$method->getConfigData("default_currency");
        }
        else if ( $object instanceof Mage_Sales_Model_Quote )
        {
            $currencyCode = $object->getQuoteCurrencyCode();
            $baseCurrencyCode = $object->getQuoteBaseCurrencyCode();
            $paymentmethodCurrencyCode = ($targetCurrencyCode == null) ? $baseCurrencyCode : $targetCurrencyCode;//$method->getConfigData("default_currency");
        }
        else if ( $object instanceof Mage_Sales_Model_Order_Invoice )
        {
            $currencyCode = $object->getCurrencyCode();
            $baseCurrencyCode = $object->getBaseCurrencyCode();
            $paymentmethodCurrencyCode = ($targetCurrencyCode == null) ? $baseCurrencyCode : $targetCurrencyCode;//$method->getConfigData("default_currency");
        }
        
        if(!$currencyCode)
        {
            $currencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        }
        
        if(!$baseCurrencyCode)
        {
            $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
            $paymentmethodCurrencyCode = ($targetCurrencyCode == null) ? $baseCurrencyCode : $targetCurrencyCode;
        }
        
        if(!$currencyCode || !$baseCurrencyCode || !$paymentmethodCurrencyCode)
            return false;
        
        $this->_mCurrencyCode = $currencyCode;
        $this->_mBaseCurrencyCode = $baseCurrencyCode;
        $this->_mPaymentmethodCurrencyCode = $paymentmethodCurrencyCode;
        
        return true;
    }
    
    public function generateValues( $object, $method, $objectItems, $doShippingBefore = false, $refundTotal= 0, $resultArray = Array() )
    {
        $log = Mage::helper("swpcommon/log");
        if( !($object instanceof Mage_Sales_Model_Order) && !($object instanceof Mage_Sales_Model_Quote) )
        {
            $log->log("Skip calculation method since parameter object was not of type Mage_Sales_Model_Order or Mage_Sales_Model_Quote.");
            $this->_mCurrentOrderIdProcessing = -1;
            return false;
        }
        
        // Calculate standard price and tax.
        $totalArr = Array();
        $subtotalExclTax = 0;
        $subtotalInclTax = 0;
        
        if($object->getIsVirtual())
        {
            $totalArr        = $this->getTotals( $object,true );
            $subtotalExclTax = $this->getSubtotalInclOrExcl( $object, true,false );
            $subtotalInclTax = $this->getSubtotalInclOrExcl( $object, true,true );
        }
        else
        {
            $totalArr        = $this->getTotals( $object,false );
            $subtotalExclTax = $this->getSubtotalInclOrExcl( $object, false,false );
            $subtotalInclTax = $this->getSubtotalInclOrExcl( $object, false,true );
        }
        
        $inheritedTaxRates = $this->getCalculatedInheritedTaxRates( $objectItems );
        $isSeveralRates = count($inheritedTaxRates) > 1;
        
        if( $refundTotal != 0)
            $resultArray = $this->addRefundTotal( $refundTotal, $resultArray );
        
        if($doShippingBefore)
        {
            $total = Array(
                    'code'  => 'shipping',
                    'title' => Mage::helper('sales')->__('Shipping & Handling').' ('.$object->getShippingDescription().')',
                    'value' => $object->getShippingAmount()
                );
            $totalArr[] = Mage::getModel('sales/quote_address_total')->setData( $total );
        }
        
        $ignoreList = explode(",", $method->getConfigData("order_total_ignore"));
        foreach( $totalArr as $total )
        {
            switch( $total->code )
            {
                case "subtotal":
                case "grand_total":
                case "tax":
                    break;
                
                
                
                
                ////////////////////////////////////////////////////
                // START: SHIPPING calculations.
                ////////////////////////////////////////////////////
                
                case "shipping":
                    $resultArray = $this->generateShipping( $object, $method, $inheritedTaxRates, $subtotalExclTax, $total, $resultArray );
                    break;
                
                ////////////////////////////////////////////////////
                // END: SHIPPING calculations.
                ////////////////////////////////////////////////////
                
                
                
                
                
                
                
                ////////////////////////////////////////////////////
                // START: HANDLINGFEE calculations.
                ////////////////////////////////////////////////////
                
                case "handlingfee_hosted":
                case "handlingfee":
                    $resultArray = $this->generateAndSaveHandlingfee( $object, $method, $subtotalExclTax, $resultArray );
                    break;
                
                ////////////////////////////////////////////////////
                // END: HANDLINGFEE calculations.
                ////////////////////////////////////////////////////     
                
                
                
                
                
                
                
                ////////////////////////////////////////////////////
                // START: DISCOUNT calculations.
                ////////////////////////////////////////////////////
                
                case "discount":
                    if(Mage::helper('tax')->applyTaxAfterDiscount())
                    {
                        foreach($inheritedTaxRates as $taxRate => $weight)
                        {
                            $rate = (Mage::helper('tax')->priceIncludesTax() ?  1 : 1 + ($taxRate/100) );
                            $value = $total->value * $rate;
                            
                            // Frist go to BASE Currency.
                            $value = $this->convertFromDisplayToBaseCurrency( $value );
                            // Then convert to payment currency that would be sent to Webservice.
                            $price = $this->convertFromBaseToPaymentmethodCurrency( $value * $weight / $subtotalInclTax );
                            
                            $resultArray[] = Array(
                                    "price" => sprintf("%.4f",$price),
                                    "name" => $total->getTitle() . ($isSeveralRates ? " (" . $taxRate . "%)" : ""),
                                    "qty" => 1,
                                    "tax" => $taxRate,
                                );
                        }
                    }
                    else
                    {
                        $price = $this->convertFromDisplayToPaymentmethodCurrency( $total->value );
                        $resultArray[] = Array(
                                "price" => sprintf("%.4f",$price),
                                "name" => $total->getTitle(),
                                "qty" => 1,
                                "tax" => 0,
                            );
                    }
                    break;
                
                ////////////////////////////////////////////////////
                // END: DISCOUNT calculations.
                ////////////////////////////////////////////////////     
                
                
                
                
                
                
                
                ////////////////////////////////////////////////////
                // START: DEFAULT fallback.
                //////////////////////////////////////////////////// 
                
                default:
                    if(in_array( $total->code, $ignoreList ))
                        break;
                    
                    // We must convert to paymentmethod's currency to be sent to webservice. 
                    $price = $this->convertFromDisplayToPaymentmethodCurrency( $total->value );
                    $resultArray[] = Array(
                            "price" => sprintf("%.4f",$price),
                            "name" => $total->getTitle(),
                            "qty" => 1,
                            "tax" => 0
                        );
                    break;
                
                
                ////////////////////////////////////////////////////
                // END: DEFAULT fallback.
                ////////////////////////////////////////////////////
            }
        }
        
        foreach($objectItems as $objectItem)
            $resultArray[] = $objectItem;
        
        
        $this->_mCurrentOrderIdProcessing = -1;
        return $resultArray;
    }
    
    
    public function retrieveShippingInformation($order)
    {
        $log = Mage::helper("swpcommon/log");
        $price = 0;
        $qty = 1;
        $tax = 0;

        // Currency fix. We make shipping value to use base currency and just change currency later with this.
        // This is to prevent som meaningless round problems.
        if($order instanceof Mage_Sales_Model_Order || $order instanceof Mage_Sales_Model_Order_Creditmemo)
        {
      		$store = Mage::app()->getStore($order->getStoreId());
            $customer = Mage::getModel('customer/customer')->load(
                    $order->getCustomerId()
                );

            $taxCalc = Mage::getSingleton('tax/calculation');
    		$taxRequest = $taxCalc->getRateRequest(
    			    $order->getShippingAddress(),
                    $order->getBillingAddress(),
                    $customer->getTaxClassId(),
                    $store
                );
            
            $taxRate = $taxCalc->getRate(
                $taxRequest->setProductClassId(
                        Mage::getStoreConfig('tax/classes/shipping_tax_class')
                    )
                );
           
            $price = (float)$order->getData('base_shipping_incl_tax');
            $tax = $taxRate;
            
            $taxRate /= 100;
			$price -= (float)$order->getData('base_shipping_discount_amount');
			$price = ($price / (1 + $taxRate));
        }
    
        // And ofcourse quote since this is for the frontend.
        else {
            $price = $order->getShippingAddress()->getBaseShippingAmount();
            $qty = $order->getQty();
            $tax = $order->getTaxPercent();
        }
                    
        return Array (
                "price" => sprintf("%.4f",$price),
                "qty" => $qty,
                "tax" => sprintf("%.4d", $tax)
            );   
    }
    
    private function generateShipping( $object, $method, $inheritedTaxRates, $subtotalExclTax, $total, $resultArray )
    {
        if(is_null($object))
            return $resultArray;

        $shippingInformation = $this->retrieveShippingInformation($object);
        $value = $shippingInformation["price"];
        $qty = $shippingInformation["qty"];
        $tax = $shippingInformation["tax"];
        
        $isSeveralRates = ((count($inheritedTaxRates) > 1) ? true : false);
        if(!$this->isQuoteVirtual( $object ))
        {
            if($method->getConfigData( "shipping_tax_override" ) == 1)
            {
                foreach( $inheritedTaxRates as $rate => $weight )
                {
                    $price = $this->convertFromBaseToPaymentmethodCurrency(($value * $weight / $subtotalExclTax));
                    $resultArray[] = Array (
                            "price" => sprintf("%.4f",$price),
                            "name" => $total->getTitle() . ( $isSeveralRates ? " (". $rate . "%)" : ""),
                            "qty" => 1,
                            "tax" => sprintf("%.4d", $rate)
                        );
                }
            }
            else
            {
                $shippingTaxClass = Mage::helper('tax')->getShippingTaxClass(Mage::app()->getStore());
                $address          = $object->getShippingAddress();
                
                $custTaxClassId      = $address->getCustomerTaxClassId();
                $taxCalculationModel = Mage::getSingleton('tax/calculation');
                
                $request = $taxCalculationModel->getRateRequest($address, $address->getBillingAddress(), $custTaxClassId, $object->getStore());
                $taxRate = $taxCalculationModel->getRate($request->setProductClassId($shippingTaxClass));
                
                $value = $this->convertFromBaseToPaymentmethodCurrency( $value );
                $resultArray[] = Array (
                        "price" => sprintf("%.4f",$value),
                        "name" => $total->getTitle(),
                        "qty" => 1,
                        "tax" => sprintf("%.4d", $taxRate)
                    );
            }
        }
        return $resultArray;
    }
    
    /**
    * 
    * Let our calculations be based on a specific order.
    * Must be used if we want to save our handlingfee if we have any.
    * @param string $orderId /// 10000001
    */
    public function setCurrentOrderIdToProcessing( $orderId = -1 )
    {
        $this->_mCurrentOrderIdProcessing = $orderId;
    }
    
    /**
    * 
    * Get totals from object(quote,order,address) is holds information as
    * grand_total,shipping,discount and more.
    * @param Mage_Sales_Model_Quote $object
    * @param boolean $flagBillingAddress
    */
    private function getTotals( $object,$flagBillingAddress = true )
    {
        $quote = null;
        if( $object instanceof Mage_Sales_Model_Order )
            $quote = Mage::getModel( "sales/quote" )->load( $object->getQuoteId() );
        else
            $quote = $object;
        
        // Skip if we couldn't find a quote.
        if($quote == null)
        {
            $log = Mage::helper("swpcommon/log");
            $log->log("Skip calculation of get totals, quote seems to be null.");
            return Array();
        }
        
        if($flagBillingAddress === true)
            return $quote->getBillingAddress()->getTotals();
            
        return $quote->getShippingAddress()->getTotals();
    }
    
    /**
    * 
    * Get information to calculate by a quote object.
    * @param Mage_Sales_Model_Quote $quote
    * @param Array $array
    * @return Array Result information.
    */
    public function getQuoteItems( $quote,$array = Array() )
    {
        $log = Mage::helper("swpcommon/log");
        if(!$quote)
            return $array;

        foreach( $quote->getAllItems() as $quoteItem )
        {
            if(!$quoteItem || $quoteItem->getParentItem())
                continue;
                
            $price = 0;
            $qty = 1;
            $tax = 0;
              
            // We execute this method from different places where we have different objects to lend.
            // One is used for the purchase and one is used for display.
            if($quote instanceof Mage_Sales_Model_Order)
            {			
     			$qty = (float)$quoteItem->getData('qty_ordered');
                $price = ((float)$quoteItem->getData('base_row_total_incl_tax') / $qty);
    			$baseTaxAmount = ((float)$quoteItem->getData('base_tax_amount') / $qty);
                $price -= ((float)$quoteItem->getData('base_discount_amount') / $qty);
                
                $taxPercentage = $quoteItem->getTaxPercent(); //(int)(($baseTaxAmount / (($price) - $baseTaxAmount)) * 100.0);
                $taxRate = $taxPercentage / 100;
    			$price = (($price / (1 + $taxRate)));
            }
            else
            {
                // @todo, this might have to be changed to base.
                $price = $quoteItem->getBasePrice();
                $qty = $quoteItem->getQty();
                $taxPercentage = sprintf("%.2d",$quoteItem->getTaxPercent());
            }
            
            $price = $this->convertFromBaseToPaymentmethodCurrency( $price );
            if($price == null)
            {
                if($quoteItem->getBasePrice() == null || $quoteItem->getBasePrice() == 0)
                {
                    $log->log("Failed to get base price from quote now setting transaction-item price to price instead.");
                    $price = $quoteItem->getPrice();
                }
                $log->log("Could not convert base price, this usaually is due to a setup failure of currencies.");
            }

            $array[] = Array(
                    "name"  => $quoteItem->getName(),
                    "price" => $price,
                    "qty"   => $qty,
                    "tax"   => sprintf("%.2d",$taxPercentage)
                );
        }
        return $array;
    }
  
    
    /**
    * 
    * Get information to calculate by a invoice object.
    * @param Mage_Sales_Model_Order_Invoice $invoice
    * @param Array $array
    * @return Array Result information.
    */
    public function getInvoiceItems( $invoice, $array = Array() )
    {
        foreach( $invoice->getAllItems() as $item )
        {
            $orderItem = $item->getOrderItem();
            if($orderItem->getParentItem())
                continue;
            
            $price = 0;
            $qty = 0;
            $tax_percentage = 0;
                
            if($invoice instanceof Mage_Sales_Model_Order_Invoice)
            {
     			$qty = (float)$orderItem->getData('qty_ordered');
                $price = ((float)$orderItem->getData('base_row_total_incl_tax') / $qty);
    			$baseTaxAmount = ((float)$orderItem->getData('base_tax_amount') / $qty);
                $price -= ((float)$orderItem->getData('base_discount_amount') / $qty);
                
                $taxPercentage = $orderItem->getTaxPercent(); //(int)(($baseTaxAmount / (($price) - $baseTaxAmount)) * 100.0);
                $taxRate = $taxPercentage / 100;
    			$price = (($price / (1 + $taxRate)));
            }
            else
            {
                $price = $orderItem->getPrice();
                $qty = $orderItem->getQty();
                $taxPercentage = sprintf("%.2d",$orderItem->getTaxPercent());
            }

            $price = $this->convertFromBaseToPaymentmethodCurrency( $price );
            $array[] = Array(
                    "name"  => $orderItem->getName(),
                    "price" => sprintf("%.4f",$price),
                    "qty"   => $qty,
                    "tax"   => sprintf("%.4d",$taxPercentage)
                );
        }
        return $array;
    }
    
    /**
    * 
    * Get information to calculate by a creditmemo object.
    * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
    * @param Array $array
    * @return Array Result information.
    */ 
    public function getCreditmemoItems( $creditmemo,$array = Array() )
    {
        foreach( $creditmemo->getAllItems() as $item )
        {
            $orderItem = $item->getOrderItem();
            if($orderItem->getParentItem())
                continue;
                
            $price = 0;
            $qty = 1;
            $tax = 0;

            if($creditmemo instanceof Mage_Sales_Model_Order_Creditmemo)
            {
     			$qty = (float)$orderItem->getData('qty_ordered');
                $price = ((float)$orderItem->getData('base_row_total_incl_tax') / $qty);
    			$baseTaxAmount = ((float)$orderItem->getData('base_tax_amount') / $qty);
                $price -= ((float)$orderItem->getData('base_discount_amount') / $qty);
                
                $taxPercentage = $orderItem->getTaxPercent(); //(int)(($baseTaxAmount / (($price) - $baseTaxAmount)) * 100.0);
                $taxRate = $taxPercentage / 100;
    			$price = (($price / (1 + $taxRate)));
            }
            else
            {
                $price = $orderItem->getPrice();
                $qty = $orderItem->getQty();
                $taxPercentage = sprintf("%.2d",$orderItem->getTaxPercent());
            }
            
            $price = $this->convertFromBaseToPaymentmethodCurrency( $price );
            $array[] = Array(
                    "name"  => $item->getName(),
                    "price" => $price,
                    "qty"   => $qty,
                    "tax"   => $taxPercentage
                );
        }
        return $array;
    }
    
    /**
    * 
    * Add amount total of refund to information array that holds calculated information.
    * @param float $currencyRate
    * @param float $total
    * @param Array $arrayResult
    * @return Array Result array with refund information added.
    */
    private function addRefundTotal( $total,$arrayResult = Array())
    {
        $total = $this->convertFromBaseToPaymentmethodCurrency( $total );
        $arrayResult[] = Array(
                "name"       => "Refund Total Specified By Customer.",
                "price"      => sprintf("%.4f",$total),
                "qty"        => 1,
                "tax"        => 0
            );
        return $arrayResult;
    }
    
    /**
    * 
    * Get tax rates, combined if more than one.
    * @param Array $objectItems
    * @param Array $rates
    * @return Array
    */
    private function getCalculatedInheritedTaxRates( $objectItems,$rates = Array() )
    {
        foreach($objectItems as $objectItem)
        {
            $rate = strval($objectItem["tax"]);
            if(!array_key_exists($rate, $rates))
            $rates[ $rate ] = 0;
            $rates[ $rate ] += $objectItem["price"] * $objectItem["qty"];
        }
        return $rates;
    }
    
    /**
    * 
    * Check if quote is virtual or not.
    * @param Mage_Sales_Model_Quote $object
    */
    private function isQuoteVirtual( $object = null )
    {
        if($object === null)
        {
            $log = Mage::helper("swpcommon/log");
            $log->log("Could not verify if quote is virtual since object doesn't exist.");
            return false;
        }
        return $object->getIsVirtual();
    }

    public function getInheritedTaxRates($object)
    {
        $rates = array();
        $items = $object->getAllItems();
        
        if(!empty($items))
        {
            foreach($items as $item)
            {
                if ($item->getParentItem())
                    continue;
                
                $rate = (string) $item->getTaxPercent();
                // set key if not exists to avoid warnings
                if (!array_key_exists($rate,$rates))
                    $rates[$rate] = 0;
                
                $price = $item->getBaseCalculationPrice();
                $price = $this->convertFromBaseToPaymentmethodCurrency( $price );
                $rates[$rate] += $price * $item->getQty();
            }
        }
        return $rates;
    }
                
    /**
    * 
    * Returns Handlingfee rate. Will be used if we only have one tax rate. 
    * @param Order,Quote $object
    * @param Method $paymentmethod
    */
    private function getFixedHandlingFeeTaxRate($object,$paymentmethod )
    {
        $pseudoRequest = new Varien_Object();
        $pseudoRequest->setProductClassId($paymentmethod->getConfigData('handling_fee_tax_class'));
        $pseudoRequest->setCustomerClassId($object->getCustomerTaxClassId()); 
        $pseudoRequest->setCountryId($object->getShippingAddress()->getCountry()); 
        return Mage::helper('tax')->getCalculator()->getRate( $pseudoRequest );
    }
    
    /**
    * 
    * Checks if handlingfee has been includeded when we created invoice.
    * @param Order $order
    */
    public function isHandlingfeeInvoiced($order)
    {
        $id = $this->getHandlingfeeInvoiceId( $order );
        if($id === null)
            return false;
            
        return true;
    }
    
    /**
    * 
    * If handlingfee was included while creating invoice, we return id of the created invoice.
    * @param Order $order
    */
    public function getHandlingfeeInvoiceId( $order )
    {
        if(!$order)
            return null;
        
        $handlingfeestore = Mage::getModel("swpcommon/handlingfeestore");
        $collection = $handlingfeestore->getCollection()->addFilter("order_id",$order->getIncrementId());
        $collection->load();
        
        if(!$collection || count($collection) <= 0)
            return null;
        
        $invoiceId = null;
        foreach($collection as $node)
            if($node->getInvoiceId() != "")
                return $node->getInvoiceId();

        return null;
    }
    
    /**
    * 
    * Get the total of handlingfee.
    * @param Order $order
    * @param string $invoiced_by
    */
    public function getHandlingfeeTotal( $order,$invoiced_by = false )
    {
        $result = Array(
                "tax"        => 0,
                "base_tax"   => 0,
                "value"      => 0,
                "base_value" => 0
            );
            
        $totals = $this->getHandlingfee( $order,$invoiced_by );
        foreach( $totals as $total )
        {
            $result["tax"]        += $total["tax"];
            $result["base_tax"]   += $total["base_tax"];
            $result["value"]      += $total["value"];
            $result["base_value"] += $total["base_value"];
        }
        return $result;
    }
    
    /**
    * 
    * Retrieve handlingfee information stored in database.
    * @param Order $order
    * @param string $invoiced_by
    */
    public function getHandlingfee( $order,$invoiced_by = false )
            
    {
        $result = Array();
        if(!$order)
            return $result;
        
        $id = $order->getIncrementId();
        if(!$id)
            return $result;
        
        $tableName = (!$invoiced_by) ? "order_id" : "invoiced_by";
        $collection =  Mage::getModel("swpcommon/handlingfeestore")->getCollection()->addFilter( $tableName, $id )->load();
        if(!$collection)
            return $result;
        
        foreach($collection as $handlingfee)
        {
            $percentRate = $handlingfee->getHandlingfeeTaxRate();
            $value       = $handlingfee->getHandlingfeeAmount();
            $baseValue   = $handlingfee->getHandlingfeeBaseAmount();
            $tax         = $value     * ( $percentRate / 100);
            $baseTax     = $baseValue * ( $percentRate / 100);
            
            $result[] = Array
                (
                    "tax"        => $tax,
                    "base_tax"   => $baseTax,
                    "value"      => $value,
                    "base_value" => $baseValue,
                    "percent"    => $percentRate
                ); 
        }
        return $result;
    }
    
    /**
    * 
    * Calculate handlingfee that will be stored in database later.
    * @param Quote,Order $quote
    * @param Quote_Address $address
    * @param Method $method
    * @param boolean $convert
    */
    public function calculateHandlingfee( $quote, $method, $convert = false, $inheritedTaxRates = null)
    {
        $result = Array();
        if ($method->getConfigData('handling_fee') == 0)
            return $result;
        
        $fee = $method->getConfigData('handling_fee_value');
        $feeIncludesTax = $method->getConfigData('handling_fee_includes_tax') == 1;
        
        $quote = ($quote instanceOf Mage_Sales_Model_Quote) ? $quote : $quote->getQuote();

        $address      = $quote->getShippingAddress();
        $subtotal     = $address->getSubtotal();
        $baseSubtotal = $address->getBaseSubtotal();
        $baseShipping = $address->getBaseShipping();
        $baseDiscount = $address->getBaseDiscountAmount();
        
        // handle handling fee as percent
        if (substr($fee,-1)=='%')
        {
            $fee = substr($fee,0,-1) / 100;
            $total =  $baseSubtotal + $baseShipping + $baseDiscount;
            $fee *= $total;
        }

        if( $method->getConfigData('handling_fee_inherit') == 1 )
        {
            if(!$inheritedTaxRates)
                $inheritedTaxRates = $this->getInheritedTaxRates( $quote );
            
            $fee1 = $this->convertFromPaymentmethodToBaseCurrency( $fee );
            if($fee1 == null)
                return $result;
            
            foreach($inheritedTaxRates as $ratePercent => $weight)
            {
                $rate = (1 + $ratePercent / 100);
                $feeRate = ($feeIncludesTax) ? $rate : 1;
                if( $subtotal <= 0 || $baseSubtotal <= 0)
                    continue;
                
                // Address works here aswell.
                $priceExcl     = $fee1 / $feeRate * $weight / $baseSubtotal;
                $priceExcl     = $this->convertFromBaseToDisplayCurrency( $priceExcl );
                $basePriceExcl = $fee / $feeRate * $weight / $baseSubtotal;
                
                $result[] = Array(
                        "weight"          => $weight,
                        "rate"            => $ratePercent,
                        "value_incl"      => sprintf("%.4f",$priceExcl * $rate),
                        "value_excl"      => sprintf("%.4f",$priceExcl),
                        "base_value_excl" => sprintf("%.4f",$basePriceExcl),
                        "tax"             => sprintf("%.4f",$priceExcl * ( $ratePercent / 100)),
                        "base_tax"        => sprintf("%.4f",$basePriceExcl * ( $ratePercent / 100))
                    );
            }
        }
        else
        {
            $fee1 = $this->convertFromPaymentmethodToBaseCurrency( $fee );
            if($fee1 == null)
                return $result;
            
            // Must be done with quote.
            $ratePercent = $this->getFixedHandlingFeeTaxRate( $quote, $method );
            $rate = (1 + ($ratePercent/100));
            $feeRate = ($feeIncludesTax) ? 1 : $rate;
            
            $priceExcl     = $fee1 / $feeRate;
            $priceExcl     = $this->convertFromBaseToDisplayCurrency( $priceExcl );
            $basePriceExcl = $fee / $feeRate;
            
            $result[] = Array(
                    "weight"          => 1, // Must be done with quote
                    "rate"            => $this->getFixedHandlingFeeTaxRate( $quote, $method ),
                    "value_incl"      => sprintf("%.2f", $priceExcl * $rate ),
                    "value_excl"      => sprintf("%.2f", $priceExcl ),
                    "base_value_excl" => sprintf("%.2f", $basePriceExcl ),
                    "tax"             => sprintf("%.2f", $priceExcl * ( $ratePercent / 100) ),
                    "base_tax"        => sprintf("%.2f", $basePriceExcl * ( $ratePercent / 100) )
                );
        }
        
        if ($convert)
        {
            foreach( $result as $key => $res )
            {        
                $result[$key]["value_incl"] = $quote->getStore()->convertPrice($res["value_incl"]);
                $result[$key]["value_excl"] = $quote->getStore()->convertPrice($res["value_excl"]);
                $result[$key]["tax"]        = $quote->getStore()->convertPrice($res["tax"]);
            }
        }
        return $result;
    }
    
    /**
    * 
    * Returns handlingfee information in a format that is used to send it to webservice.
    * Or hosted.
    * @param float $price
    * @param string $description
    * @param int $rate
    * @param boolean $moreThanOneHandlingfee
    */
    public function buildHandlingFeeArray($price,$description,$rate, $moreThanOneHandlingfee = false)
    {
        return Array(
                "price" => $price,
                "name" => $description  . (($moreThanOneHandlingfee) ? " (" . $rate . "%)" : ""),
                "qty" => 1,
                "tax" => sprintf("%.2d",$rate)
            );
    }
    
    /**
    * 
    * Calculate handlingfee and call save method.
    * @param $quote
    * @param $inheritedTaxRates
    * @param $isSeveralRates
    * @param $currencyRate
    * @param $total
    * @param $subtotalExclTax
    * @param $description
    * @param $arrayResult
    */
    private function generateAndSaveHandlingfee( $quote, $method, $subtotalExclTax, $arrayResult = Array() )
    {
        if($this->_mCurrentOrderIdProcessing != -1)
        {
            $description = $method->getConfigData('handling_fee_description');
            $handlingFees = $this->calculateHandlingfee( $quote,  $method );
            
            foreach( $handlingFees as $key => $handlingFee )
            {
                $rate            = $handlingFee["rate"];
                $weight          = $handlingFee["weight"];
                $value_excl      = $handlingFee["value_excl"];
                $base_value_excl = $handlingFee["base_value_excl"];
                
                $price     = sprintf("%.4f",$value_excl);
                $basePrice = sprintf("%.4f",$base_value_excl);

                $arrayResult[] = Array (
                        "price" => $basePrice,
                        "name" => $description  . ((count($handlingFees) > 1) ? " (" . $rate . "%)" : ""),
                        "qty" => 1,
                        "tax" => sprintf("%.4d",$rate)
                    );
                
                // Save handlingfee.
                $this->saveHandlingFeeStoreInformation( $quote, $price, $basePrice,$rate );
            }
        }
        return $arrayResult;
    }
    
    /**
    * 
    *  Save handlingfee information.
    * @param $object
    * @param $total
    * @param $taxRate
    */
    private function saveHandlingFeeStoreInformation( $object, $price, $basePrice, $taxRate )
    {
        $log = Mage::helper("swpcommon/log");
        try
        {
            $handlingfeeStore = Mage::getModel( "swpcommon/handlingfeestore" );
            $handlingfeeStore->setOrderId( $this->_mCurrentOrderIdProcessing );
            $handlingfeeStore->setHandlingfeeAmount( $price );
            $handlingfeeStore->setHandlingfeeBaseAmount( $basePrice );
            $handlingfeeStore->setHandlingfeeTaxRate( $taxRate );
            $handlingfeeStore->save();
            
        }
        catch(Exceptin $e)
        {
            $log->log("Exception has been caught: " . var_export($e,true));
        }
    }
    
    /**
    * 
    * Get subtotal Including or excluding tax, depending on incl flag.
    * @param Order,Quote $object
    * @param boolean $flagBilling
    * @param boolean $flagIncl
    */
    private function getSubtotalInclOrExcl( $quote,$flagBilling = true,$flagIncl = true )
    { 
        if($quote == null)
        {
            $log = Mage::helper("swpcommon/log");
            $log->log("Will not try to retrive subtotal include or exclude information, since object to do so was not found.");
            return false;
        }
        
        $methodNameToRun = (($flagIncl == true) ? "getBaseSubtotalInclTax" : "getBaseSubtotal");
        if( $quote instanceof Mage_Sales_Model_Order )
            return $quote->$methodNameToRun();
        
        if($flagBilling)
            return $quote->getBillingAddress()->$methodNameToRun();
             
        return $quote->getShippingAddress()->$methodNameToRun();
    }
}