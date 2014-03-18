<?php
$root = realpath(dirname(__FILE__));

require_once $root . '/../../../../src/Includes.php';
require_once $root . '/../../../../src/WebServiceRequests/svea_soap/SveaSoapConfig.php';

/**
 * @author Kristian Grossman-Madsen for Svea Webpay
 */
class LowerTransactionTest extends PHPUnit_Framework_TestCase {
        
    protected $configObject;
    protected $lowerTransactionObject;

    // fixture, run once before each test method
    protected function setUp() {
        $this->configObject = Svea\SveaConfig::getDefaultConfig();
        $this->lowerTransactionObject = WebPay::lowerTransaction( $this->configObject );
    }

    // test methods
    function test_class_exists(){
        
        $this->assertInstanceOf( "Svea\LowerTransaction", $this->lowerTransactionObject);      
    }
    
    function test_setCountryCode(){
        
        $countryCode = "SE";       
        $this->lowerTransactionObject->setCountryCode( $countryCode );
        
        $this->assertEquals( $countryCode, PHPUnit_Framework_Assert::readAttribute($this->lowerTransactionObject, 'countryCode') );
    }
    
    function test_setTransactionId( ){
        
        $transactionId = 987654;       
        $this->lowerTransactionObject->setTransactionId( $transactionId );
        
        $this->assertEquals( $transactionId, PHPUnit_Framework_Assert::readAttribute($this->lowerTransactionObject, 'transactionId') );
    }
    
    function test_setAmountToLower() {
        
        $amountToLower = 100;
        $this->lowerTransactionObject->setAmountToLower( $amountToLower );
        
        $this->assertEquals( $amountToLower, PHPUnit_Framework_Assert::readAttribute($this->lowerTransactionObject, 'amountToLower') );
    }
              
    function test_prepareRequest_array_contains_mac_merchantid_message() {

        // set up lowerTransaction object & get request form
        $transactionId = 987654;       
        $this->lowerTransactionObject->setTransactionId( $transactionId );

        $amountToLower = 100;
        $this->lowerTransactionObject->setAmountToLower( $amountToLower );
        
        $countryCode = "SE";
        $this->lowerTransactionObject->setCountryCode($countryCode);
                
        $form = $this->lowerTransactionObject->prepareRequest();

        // prepared request is message (base64 encoded), merchantid, mac
        $this->assertTrue( isset($form['merchantid']) );
        $this->assertTrue( isset($form['mac']) );
        $this->assertTrue( isset($form['message']) );
    }
    
    function test_prepareRequest_has_correct_merchantid_mac_and_lowerTransaction_request_message_contents() {

        // set up lowerTransaction object & get request form
        $transactionId = 987654;       
        $this->lowerTransactionObject->setTransactionId( $transactionId );

        $amountToLower = 100;
        $this->lowerTransactionObject->setAmountToLower( $amountToLower );
     
        $countryCode = "SE";
        $this->lowerTransactionObject->setCountryCode($countryCode);
                
        $form = $this->lowerTransactionObject->prepareRequest();
        
        // get our merchantid & secret
        $merchantid = $this->configObject->getMerchantId( ConfigurationProvider::HOSTED_TYPE, $countryCode);
        $secret = $this->configObject->getSecret( ConfigurationProvider::HOSTED_TYPE, $countryCode);
         
        // check mechantid
        $this->assertEquals( $merchantid, urldecode($form['merchantid']) );

        // check valid mac
        $this->assertEquals( hash("sha512", urldecode($form['message']). $secret), urldecode($form['mac']) );
        
        // check credit request message contents
        $xmlMessage = new SimpleXMLElement( base64_decode(urldecode($form['message'])) );

        $this->assertEquals( "loweramount", $xmlMessage->getName() );   // root node        
        $this->assertEquals((string)$transactionId, $xmlMessage->transactionid);
        $this->assertEquals((string)$amountToLower, $xmlMessage->amounttolower);        
    }
}
?>
