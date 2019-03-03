<?php
/**
 * QuickBooks Online API class
 */

require_once( 'vendor/autoload.php' );

use QuickBooksOnline\API\Core\ServiceContext;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\PlatformService\PlatformService;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Facades\Customer;
use QuickBooksOnline\API\Facades\Invoice;

class WCQBI_QBO_API_Client {
	protected $option_name;
	protected $_client;
	protected $_client_id;
	protected $_client_secret;
	protected $_access_token_key;
	protected $_refresh_token_key;
	protected $_qbo_realm_id;
	protected $_base_url;
	protected $qbo_api_args;

	/**
	 * Constructor for class
	 */
	public function __construct() {
		$this->option_name  = 'wcqbi_qbo';
		$this->qbo_api_args = get_option( $this->option_name );
		$this->_client      = DataService::Configure( $this->qbo_api_args );
	}

	public function set_client() {
		$OAuth2LoginHelper = $this->_client->getOAuth2LoginHelper();
		$access_token = $OAuth2LoginHelper->refreshToken();
		$this->_client->throwExceptionOnError(true);
		$this->update_tokens( $access_token );
	}

	public function redirect_to_auth() {
		$OAuth2LoginHelper = $this->_client->getOAuth2LoginHelper();
		$url = $OAuth2LoginHelper->getAuthorizationCodeURL();
		header( 'Location: ' . $url );
	}

	public function update_tokens( $access_token ) {
		$options                    = get_option( $this->option_name );
		$options['accessTokenKey']  = $access_token->getAccessToken();
		$options['refreshTokenKey'] = $access_token->getRefreshToken();
		update_option( $this->option_name, $options );

		$this->_client->updateOAuth2Token( $access_token );
	}

	public function set_auth_tokens( $code, $realm_id ) {
		$OAuth2LoginHelper     = $this->_client->getOAuth2LoginHelper();
		$access_token          = $OAuth2LoginHelper->exchangeAuthorizationCodeForToken( $code, $realm_id );
		$options               = get_option( $this->option_name );
		$options['QBORealmID'] = $realm_id;

		update_option( $this->option_name, $options );
		$this->update_tokens( $access_token );

		wp_redirect( $this->qbo_api_args['RedirectURI'] );
		header( "Location: " . $this->qbo_api_args['RedirectURI'] );
	}

	public function get_invoices() {
		return $this->_client->Query( $this->get_sql_select_query() );
	}

	public function get_company_info() {
		return $company = $this->_client->getCompanyInfo();
	}

	public function get_last_error() {
		return $this->_client->getLastError();
	}

	public function get_customer_by_id( $customer_ref ) {
		return $this->_client->FindbyId( 'customer', $customer_ref );
	}

	public function get_sql_select_query() {
		$last_fetched = get_option( 'wcqbi_last_update', '' );

		$select_sql = "SELECT * FROM Invoice";
		if ( '' !== $last_fetched ) {
			$select_sql = "SELECT * FROM Invoice WHERE MetaData.CreateTime >= '" . $last_fetched . "' ORDERBY MetaData.CreateTime";
		}

		return $select_sql;
	}
}
