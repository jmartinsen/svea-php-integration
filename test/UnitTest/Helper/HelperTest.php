<?php

namespace Svea\WebPay\Test\UnitTest\Helper;

use Svea\WebPay\WebPay;
use Svea\WebPay\WebPayItem;
use Svea\WebPay\Helper\Helper;
use Svea\WebPay\Test\TestUtil;
use Svea\WebPay\Config\ConfigurationService;
use Svea\WebPay\WebService\GetPaymentPlanParams\GetPaymentPlanParams;
use Svea\WebPay\WebService\WebServiceResponse\PaymentPlanParamsResponse;

class HelperTest extends \PHPUnit\Framework\TestCase
{

    // Helper::bround() is an alias for round(x,0,PHP_ROUND_HALF_EVEN)
    function test_bround_RoundsHalfToEven()
    {
        $this->assertEquals(1, Helper::bround(0.51));
        $this->assertEquals(1, Helper::bround(1.49));
        $this->assertEquals(2, Helper::bround(1.5));

        $this->assertEquals(1, Helper::bround(1.49999999999999)); //seems to work with up to 14 decimals, then float creep pushes us over 1.5
        $this->assertEquals(2, Helper::bround(1.500000000000000000000000000000000000000000));
        $this->assertEquals(1, Helper::bround(1.0));
        $this->assertEquals(1, Helper::bround(1));
        //$this->assert( 1, bround("1") );     raise illegalArgumentException??

        $this->assertEquals(4, Helper::bround(4.5));
        $this->assertEquals(6, Helper::bround(5.5));

        $this->assertEquals(-1, Helper::bround(-1.1));
        $this->assertEquals(-2, Helper::bround(-1.5));

        $this->assertEquals(0, Helper::bround(-0.5));
        $this->assertEquals(0, Helper::bround(0));
        $this->assertEquals(0, Helper::bround(0.5));

        $this->assertEquals(262462, Helper::bround(262462.5));

        $this->assertEquals(0.479, Helper::bround(0.4785375, 3));  // i.e. greater than 0.4585, so round up
        $this->assertEquals(0.478, Helper::bround(0.4780000, 3));  // i.e. exactly 0.4585, so round to even
    }

    //--------------------------------------------------------------------------

    function test_splitMeanToTwoTaxRates_returnType()
    {

        $discountAmountIncVat = 100;
        $discountVatAmount = 18.6667;
        $discountName = 'Coupon(1112)';
        $discountDescription = '-100kr';
        $allowedTaxRates = array(25, 6);

        $discountRows = Helper::splitMeanToTwoTaxRates($discountAmountIncVat, $discountVatAmount, $discountName, $discountDescription, $allowedTaxRates);

        $this->assertTrue(is_array($discountRows));
        $this->assertTrue(is_a($discountRows[0], 'Svea\WebPay\BuildOrder\RowBuilders\FixedDiscount'));
    }

    function test_splitMeanToTwoTaxRates_splitTwoRates()
    {

        $discountAmountExVat = 100;
        $discountVatAmount = 18.6667;
        $discountName = 'Coupon(1112)';
        $discountDescription = '-100kr';
        $allowedTaxRates = array(25, 6);

        $discountRows = Helper::splitMeanToTwoTaxRates($discountAmountExVat, $discountVatAmount, $discountName, $discountDescription, $allowedTaxRates);

        // 200 + 50 (25%)
        // 100 + 6 (6%)
        // -100 => 200/300 @25%, 100/300 @6%
        // => 2/3 * -100 + 2/3 * -25 discount @25%, 1/3 * -100 + 1/3 * -6 discount @6% => -100 @ 18,6667%

        $this->assertEquals(66.67, $discountRows[0]->amountExVat);
        $this->assertEquals(25, $discountRows[0]->vatPercent);
        $this->assertEquals('Coupon(1112)', $discountRows[0]->name);
        $this->assertEquals('-100kr (25%)', $discountRows[0]->description);

        $this->assertEquals(33.33, $discountRows[1]->amountExVat);
        $this->assertEquals(6, $discountRows[1]->vatPercent);
        $this->assertEquals('Coupon(1112)', $discountRows[1]->name);
        $this->assertEquals('-100kr (6%)', $discountRows[1]->description);
    }

    function test_splitMeanToTwoTaxRates_splitTwoRates_2()
    {

        $discountAmountExVat = 100;
        $discountVatAmount = 15.5;
        $discountName = 'Coupon(1112)';
        $discountDescription = '-100kr';
        $allowedTaxRates = array(25, 6);

        $discountRows = Helper::splitMeanToTwoTaxRates($discountAmountExVat, $discountVatAmount, $discountName, $discountDescription, $allowedTaxRates);

        // 1000 + 250 (25%)
        // 1000 + 60 (6%)
        // -100 => 1000/2000 @25%, 1000/2000 @6%
        // => 0,5 * -100 + 0,5 * -25 discount @25%, 0,5 * -100 + 0,5 * -6 discount @6%  => -100 @ 15,5%

        $this->assertEquals(50.0, $discountRows[0]->amountExVat);
        $this->assertEquals(25, $discountRows[0]->vatPercent);
        $this->assertEquals('Coupon(1112)', $discountRows[0]->name);
        $this->assertEquals('-100kr (25%)', $discountRows[0]->description);

        $this->assertEquals(50.0, $discountRows[1]->amountExVat);
        $this->assertEquals(6, $discountRows[1]->vatPercent);
        $this->assertEquals('Coupon(1112)', $discountRows[1]->name);
        $this->assertEquals('-100kr (6%)', $discountRows[1]->description);
    }

    // TODO move below from Svea\WebPay\Test\UnitTest\WebService\Helper\WebServiceRowFormatterTest (modified to use Helper::splitMeanToTwoTaxRates) to integrationtest for Helper
    //public function testFormatFixedDiscountRows_amountExVatAndVatPercent_WithDifferentVatRatesPresent2() {

    //--------------------------------------------------------------------------

    function test_getAllTaxRatesInOrder_returnType()
    {
        $config = ConfigurationService::getDefaultConfig();
        $order = WebPay::createOrder($config);
        $order->addOrderRow(WebPayItem::orderRow()
            ->setAmountExVat(100.00)
            ->setVatPercent(25)
            ->setQuantity(2)
        );

        $taxRates = Helper::getTaxRatesInOrder($order);

        $this->assertTrue(is_array($taxRates));
    }

    function test_getAllTaxRatesInOrder_getOneRate()
    {
        $config = ConfigurationService::getDefaultConfig();
        $order = WebPay::createOrder($config);
        $order->addOrderRow(WebPayItem::orderRow()
            ->setAmountExVat(100.00)
            ->setVatPercent(25)
            ->setQuantity(2)
        );

        $taxRates = Helper::getTaxRatesInOrder($order);

        $this->assertEquals(1, sizeof($taxRates));
        $this->assertEquals(25, $taxRates[0]);
    }

    function test_getAllTaxRatesInOrder_getTwoRates()
    {
        $config = ConfigurationService::getDefaultConfig();
        $order = WebPay::createOrder($config);
        $order->addOrderRow(WebPayItem::orderRow()
            ->setAmountExVat(100.00)
            ->setAmountIncVat(125.00)
            ->setQuantity(2)
        )
            ->addOrderRow(WebPayItem::orderRow()
                ->setAmountExVat(100.00)
                ->setVatPercent(6)
                ->setQuantity(1)
            );

        $taxRates = Helper::getTaxRatesInOrder($order);

        $this->assertEquals(2, sizeof($taxRates));
        $this->assertEquals(25, $taxRates[0]);
        $this->assertEquals(6, $taxRates[1]);
    }

    function test_getSveaLibraryProperties()
    {
        $libraryPropertiesArray = Helper::getSveaLibraryProperties();
        $this->assertTrue(array_key_exists("library_name", $libraryPropertiesArray));
        $this->assertTrue(array_key_exists("library_version", $libraryPropertiesArray));
    }

    /// new implementation of splitMeanAcrossTaxRates helper method
    //  1u. mean ex to single tax rate: 10e @20% -> 12i @25% 
    //  2u. mean inc to single tax rate: 12i @20% -> 12i @25%
    //  3i. mean inc to single tax rate: 12i @20% -> 12i @25%, priceincvat = true => correct order total at Svea
    //  4i. mean inc to single tax rate: 12i @20% -> 12i @25%, priceincvat = false -> resent as 9.6e @25%, priceincvat = false => correct order total at Svea
    //  5u. mean ex to two tax rates: 8.62e @16% -> 5.67i @25%; 4.33i @6%
    //  6u. mean inc to two tax rate: 10i @16 % -> 5.67i @25%; 4.33i @6%
    //  7i. mean inc to two tax rates: 8.62e @16% -> 5.67i @25%; 4.33i @6%, priceincvat = true => correct order total at Svea
    //  8i. mean inc to two tax rates: 10i @16 % -> 5.67i @25%; 4.33i @6%, priceincvat = false -> resent w/priceincvat = false => correct order total at Svea
    //  9u. mean ex to single tax rate with mean vat rate zero: resend as single row
    //  10u. mean ex to two tax rates with mean vat rate zero: resend as single row

    //  1u. mean ex to single tax rate: 10e @20% -> 12i @25%
    function test_splitMeanAcrossTaxRates_1()
    {
        $discountAmount = 10.0;
        $discountGivenExVat = true;
        $discountMeanVatPercent = 20.0;
        $discountName = 'Name';
        $discountDescription = 'Description';
        $allowedTaxRates = array(25);

        $discountRows = Helper::splitMeanAcrossTaxRates(
            $discountAmount, $discountMeanVatPercent, $discountName, $discountDescription, $allowedTaxRates, $discountGivenExVat
        );

        $this->assertEquals(12, $discountRows[0]->amountIncVat);
        $this->assertEquals(25, $discountRows[0]->vatPercent);
        $this->assertEquals('Name', $discountRows[0]->name);
        $this->assertEquals('Description', $discountRows[0]->description);
        $this->assertEquals(null, $discountRows[0]->amountExVat);

        $this->assertEquals(1, count($discountRows));
    }

    //  2u. mean inc to single tax rate: 12i @20% -> 12i @25%
    function test_splitMeanAcrossTaxRates_2()
    {
        $discountAmount = 12.0;
        $discountGivenExVat = false;
        $discountMeanVatPercent = 20.0;
        $discountName = 'Name';
        $discountDescription = 'Description';
        $allowedTaxRates = array(25);

        $discountRows = Helper::splitMeanAcrossTaxRates(
            $discountAmount, $discountMeanVatPercent, $discountName, $discountDescription, $allowedTaxRates, $discountGivenExVat
        );

        $this->assertEquals(12, $discountRows[0]->amountIncVat);
        $this->assertEquals(25, $discountRows[0]->vatPercent);
        $this->assertEquals(null, $discountRows[0]->amountExVat);
    }

    //  5u. mean ex to two tax rates: 8.62e @16% -> 5.67i @25%; 4.33i @6%
    function test_splitMeanAcrossTaxRates_5()
    {
        $discountAmount = 8.62;
        $discountGivenExVat = true;
        $discountMeanVatPercent = 16.0;
        $discountName = 'Name';
        $discountDescription = 'Description';
        $allowedTaxRates = array(25, 6);

        $discountRows = Helper::splitMeanAcrossTaxRates(
            $discountAmount, $discountMeanVatPercent, $discountName, $discountDescription, $allowedTaxRates, $discountGivenExVat
        );

        $this->assertEquals(5.67, $discountRows[0]->amountIncVat);
        $this->assertEquals(25, $discountRows[0]->vatPercent);
        $this->assertEquals('Name', $discountRows[0]->name);
        $this->assertEquals('Description (25%)', $discountRows[0]->description);
        $this->assertEquals(null, $discountRows[0]->amountExVat);

        $this->assertEquals(4.33, $discountRows[1]->amountIncVat);
        $this->assertEquals(6, $discountRows[1]->vatPercent);
        $this->assertEquals('Name', $discountRows[1]->name);
        $this->assertEquals('Description (6%)', $discountRows[1]->description);
        $this->assertEquals(null, $discountRows[1]->amountExVat);

        $this->assertEquals(2, count($discountRows));
    }

    //  6u. mean inc to two tax rate: 10i @16 % -> 5.67i @25%; 4.33i @6%
    function test_splitMeanAcrossTaxRates_6()
    {
        $discountAmount = 10.0;
        $discountGivenExVat = false;
        $discountMeanVatPercent = 16.0;
        $discountName = 'Name';
        $discountDescription = 'Description';
        $allowedTaxRates = array(25, 6);

        $discountRows = Helper::splitMeanAcrossTaxRates(
            $discountAmount, $discountMeanVatPercent, $discountName, $discountDescription, $allowedTaxRates, $discountGivenExVat
        );

        $this->assertEquals(5.67, $discountRows[0]->amountIncVat);
        $this->assertEquals(25, $discountRows[0]->vatPercent);
        $this->assertEquals('Name', $discountRows[0]->name);
        $this->assertEquals('Description (25%)', $discountRows[0]->description);
        $this->assertEquals(null, $discountRows[0]->amountExVat);

        $this->assertEquals(4.33, $discountRows[1]->amountIncVat);
        $this->assertEquals(6, $discountRows[1]->vatPercent);
        $this->assertEquals('Name', $discountRows[1]->name);
        $this->assertEquals('Description (6%)', $discountRows[1]->description);
        $this->assertEquals(null, $discountRows[1]->amountExVat);

        $this->assertEquals(2, count($discountRows));
    }

    //  9u. mean ex to single tax rate with mean vat rate zero (exvat): resend as single row w/ zero vat
    function test_splitMeanAcrossTaxRates_9()
    {
        $discountAmount = 10.0;
        $discountGivenExVat = true;
        $discountMeanVatPercent = 0.0;
        $discountName = 'Name';
        $discountDescription = 'Description';
        $allowedTaxRates = array(25);

        $discountRows = Helper::splitMeanAcrossTaxRates(
            $discountAmount, $discountMeanVatPercent, $discountName, $discountDescription, $allowedTaxRates, $discountGivenExVat
        );

        $this->assertEquals(10.0, $discountRows[0]->amountIncVat);
        $this->assertEquals(0, $discountRows[0]->vatPercent);
        $this->assertEquals('Name', $discountRows[0]->name);
        $this->assertEquals('Description', $discountRows[0]->description);
        $this->assertEquals(null, $discountRows[0]->amountExVat);

        $this->assertEquals(1, count($discountRows));
    }

    //  10u. mean ex to two tax rates with mean vat rate less than zero (incvat): resend as single row w/ zero vat
    function test_splitMeanAcrossTaxRates_10()
    {
        $discountAmount = 10.0;
        $discountGivenExVat = false;
        $discountMeanVatPercent = -1;
        $discountName = 'Name';
        $discountDescription = 'Description';
        $allowedTaxRates = array(25, 6);

        $discountRows = Helper::splitMeanAcrossTaxRates(
            $discountAmount, $discountMeanVatPercent, $discountName, $discountDescription, $allowedTaxRates, $discountGivenExVat
        );

        $this->assertEquals(10.0, $discountRows[0]->amountIncVat);
        $this->assertEquals(0, $discountRows[0]->vatPercent);
        $this->assertEquals('Name', $discountRows[0]->name);
        $this->assertEquals('Description', $discountRows[0]->description);
        $this->assertEquals(null, $discountRows[0]->amountExVat);

        $this->assertEquals(1, count($discountRows));
    }

//    function test_splitMeanToTwoTaxRates_splitTwoRates() {
//
//        $discountAmountExVat = 100;
//        $discountVatAmount = 18.6667;
//        $discountName = 'Coupon(1112)';
//        $discountDescription = '-100kr';
//        $allowedTaxRates = array( 25,6 );
//
//        $discountRows = Helper::splitMeanToTwoTaxRates( $discountAmountExVat,$discountVatAmount,$discountName,$discountDescription,$allowedTaxRates );
//
//        // 200 + 50 (25%)
//        // 100 + 6 (6%)
//        // -100 => 200/300 @25%, 100/300 @6%
//        // => 2/3 * -100 + 2/3 * -25 discount @25%, 1/3 * -100 + 1/3 * -6 discount @6% => -100 @ 18,6667%
//
//        $this->assertEquals( 66.67,$discountRows[0]->amountExVat );
//        $this->assertEquals( 25, $discountRows[0]->vatPercent );
//        $this->assertEquals( 'Coupon(1112)', $discountRows[0]->name );
//        $this->assertEquals( '-100kr (25%)', $discountRows[0]->description );
//
//        $this->assertEquals( 33.33,$discountRows[1]->amountExVat );
//        $this->assertEquals( 6, $discountRows[1]->vatPercent );
//        $this->assertEquals( 'Coupon(1112)', $discountRows[1]->name );
//        $this->assertEquals( '-100kr (6%)', $discountRows[1]->description );
//    }


    //  11A. mean inc to two tax rates, 50+6/3 = 18,67% => 19%
    /**
     * @doesNotPerformAssertions
     */
    function test_splitMeanAcrossTaxRates_11a()
    {
        $discountAmount = 119.0;
        $discountGivenExVat = false;
        $discountMeanVatPercent = 19;
        $discountName = 'Name';
        $discountDescription = 'Description';
        $allowedTaxRates = array(25, 6);

        $discountRows = Helper::splitMeanAcrossTaxRates(
            $discountAmount, $discountMeanVatPercent, $discountName, $discountDescription, $allowedTaxRates, $discountGivenExVat
        );

//    print_r( $discountRows );

    }

    //  11B. mean inc to two tax rates, 50+6/3 = 18,67%
    /**
     * @doesNotPerformAssertions
     */
    function test_splitMeanAcrossTaxRates_11b()
    {
        $discountAmount = 118.67;
        $discountGivenExVat = false;
        $discountMeanVatPercent = 18.67;
        $discountName = 'Name';
        $discountDescription = 'Description';
        $allowedTaxRates = array(25, 6);

        $discountRows = Helper::splitMeanAcrossTaxRates(
            $discountAmount, $discountMeanVatPercent, $discountName, $discountDescription, $allowedTaxRates, $discountGivenExVat
        );

//    print_r( $discountRows );

    }

    function test_validCardPayCurrency()
    {
        $var = Helper::isCardPayCurrency("SEK");
        $this->assertEquals(true, $var);
    }

    function test_invalidCardPayCurrency()
    {
        $var = Helper::isCardPayCurrency("XXX");
        $this->assertEquals(false, $var);
    }

    function test_validPeppolId()
    {
        $var = Helper::isValidPeppolId("1234:abc12");
        $this->assertEquals(true, $var);
    }

    function test_invalidPeppolId()
    {
        $var = Helper::isValidPeppolId("abcd:1234"); // First 4 characters must be numeric
        $var1 = Helper::isValidPeppolId("1234abc12"); // Fifth character must be ':'.
        $var2 = Helper::isValidPeppolId("1234:ab.c12"); // Rest of the characters must be alphanumeric
        $var3 = Helper::isValidPeppolId("1234:abc12abc12abc12abc12abc12abc12abc12abc12abc12abc12abc12abc12abc12abc12abc12abc12abc12"); // String cannot be longer 55 characters
        $var4 = Helper::isValidPeppolId("1234:"); // String must be longer than 5 characters

        $this->assertEquals(false, $var);
        $this->assertEquals(false, $var1);
        $this->assertEquals(false, $var2);
        $this->assertEquals(false, $var3);
        $this->assertEquals(false, $var4);
    }

    function test_calculateCorrectPricePerMonth()
    {
        $price = 10000;

        $params = new GetPaymentPlanParams(ConfigurationService::getDefaultConfig());
        $params->campaignCodes = array (
                    0 =>
                        array(
                            'campaignCode' => 213060,
                            'description' => 'Dela upp betalningen på 60 månader',
                            'paymentPlanType' => 'Standard',
                            'contractLengthInMonths' => 60,
                            'monthlyAnnuityFactor' => '0.02555',
                            'initialFee' => '100',
                            'notificationFee' => '29',
                            'interestRatePercent' => '16.75',
                            'numberOfInterestFreeMonths' => 3,
                            'numberOfPaymentFreeMonths' => 3,
                            'fromAmount' => '1000',
                            'toAmount' => '50000',
                        ),
                    1 =>
                        array(
                            'campaignCode' => 222065,
                            'description' => 'Vårkampanj',
                            'paymentPlanType' => 'InterestAndAmortizationFree',
                            'contractLengthInMonths' => 3,
                            'monthlyAnnuityFactor' => '1',
                            'initialFee' => '0',
                            'notificationFee' => '0',
                            'interestRatePercent' => '0',
                            'numberOfInterestFreeMonths' => 3,
                            'numberOfPaymentFreeMonths' => 3,
                            'fromAmount' => '120',
                            'toAmount' => '30000',
                        ),
                    2 =>
                        array(
                            'campaignCode' => 222066,
                            'description' => 'Sommarkampanj',
                            'paymentPlanType' => 'InterestAndAmortizationFree',
                            'contractLengthInMonths' => 3,
                            'monthlyAnnuityFactor' => '1',
                            'initialFee' => '0',
                            'notificationFee' => '0',
                            'interestRatePercent' => '0',
                            'numberOfInterestFreeMonths' => 3,
                            'numberOfPaymentFreeMonths' => 3,
                            'fromAmount' => '120',
                            'toAmount' => '30000',
                        ),
                    3 =>
                        array(
                            'campaignCode' => 223060,
                            'description' => 'Köp nu betala om 3 månader (räntefritt)',
                            'paymentPlanType' => 'InterestAndAmortizationFree',
                            'contractLengthInMonths' => 3,
                            'monthlyAnnuityFactor' => '1',
                            'initialFee' => '0',
                            'notificationFee' => '29',
                            'interestRatePercent' => '0',
                            'numberOfInterestFreeMonths' => 3,
                            'numberOfPaymentFreeMonths' => 3,
                            'fromAmount' => '1000',
                            'toAmount' => '50000',
                        ),
                    4 =>
                        array(
                            'campaignCode' => 223065,
                            'description' => 'Black Friday - Cyber Monday',
                            'paymentPlanType' => 'InterestAndAmortizationFree',
                            'contractLengthInMonths' => 3,
                            'monthlyAnnuityFactor' => '1',
                            'initialFee' => '0',
                            'notificationFee' => '0',
                            'interestRatePercent' => '0',
                            'numberOfInterestFreeMonths' => 3,
                            'numberOfPaymentFreeMonths' => 3,
                            'fromAmount' => '120',
                            'toAmount' => '30000',
                        ),
                    5 =>
                        array(
                            'campaignCode' => 223066,
                            'description' => 'Julkampanj',
                            'paymentPlanType' => 'InterestAndAmortizationFree',
                            'contractLengthInMonths' => 3,
                            'monthlyAnnuityFactor' => '1',
                            'initialFee' => '0',
                            'notificationFee' => '0',
                            'interestRatePercent' => '0',
                            'numberOfInterestFreeMonths' => 3,
                            'numberOfPaymentFreeMonths' => 3,
                            'fromAmount' => '120',
                            'toAmount' => '30000',
                        ),
                    6 =>
                        array(
                            'campaignCode' => 310012,
                            'description' => 'Dela upp betalningen på 12 månader (räntefritt)',
                            'paymentPlanType' => 'InterestFree',
                            'contractLengthInMonths' => 12,
                            'monthlyAnnuityFactor' => '0.08333',
                            'initialFee' => '295',
                            'notificationFee' => '35',
                            'interestRatePercent' => '0',
                            'numberOfInterestFreeMonths' => 12,
                            'numberOfPaymentFreeMonths' => 0,
                            'fromAmount' => '1000',
                            'toAmount' => '30000',
                        ),
                    7 =>
                        array(
                            'campaignCode' => 410012,
                            'description' => 'Dela upp betalningen på 12 månader',
                            'paymentPlanType' => 'Standard',
                            'contractLengthInMonths' => 12,
                            'monthlyAnnuityFactor' => '0.09259',
                            'initialFee' => '0',
                            'notificationFee' => '29',
                            'interestRatePercent' => '19.9',
                            'numberOfInterestFreeMonths' => 0,
                            'numberOfPaymentFreeMonths' => 0,
                            'fromAmount' => '100',
                            'toAmount' => '30000',
                        ),
                    8 =>
                        array(
                            'campaignCode' => 410024,
                            'description' => 'Dela upp betalningen på 24 månader',
                            'paymentPlanType' => 'Standard',
                            'contractLengthInMonths' => 24,
                            'monthlyAnnuityFactor' => '0.04684',
                            'initialFee' => '350',
                            'notificationFee' => '35',
                            'interestRatePercent' => '11.5',
                            'numberOfInterestFreeMonths' => 0,
                            'numberOfPaymentFreeMonths' => 0,
                            'fromAmount' => '1000',
                            'toAmount' => '150000',
                        ));

        $arr = Helper::paymentPlanPricePerMonth($price, $params, true);

        $this->assertEquals(286.66666666667, $arr->values[0]['pricePerMonth']);
        $this->assertEquals(10000.0, $arr->values[1]['pricePerMonth']);
        $this->assertEquals(10000.0, $arr->values[2]['pricePerMonth']);
        $this->assertEquals(10029.0, $arr->values[3]['pricePerMonth']);
        $this->assertEquals(10000.0, $arr->values[4]['pricePerMonth']);
        $this->assertEquals(10000.0, $arr->values[5]['pricePerMonth']);
        $this->assertEquals(893.58333333333, $arr->values[6]['pricePerMonth']);
        $this->assertEquals(955.0, $arr->values[7]['pricePerMonth']);
        $this->assertEquals(518.58333333333, $arr->values[8]['pricePerMonth']);
    }
}
