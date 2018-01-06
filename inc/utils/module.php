<?php
if ( ! defined( 'ABSPATH' ) ) exit;


class machete_utils_module extends machete_module {

	function __construct(){
		$this->init( array(
			'slug' => 'utils',
			'title' => __('Analytics & Code','machete'),
			'full_title' => __('Analytics and Custom Code','machete'),
			'description' => __('Google Analytics tracking code manager and a simple editor to insert HTML, CSS and JS snippets or site verification tags.','machete'),
			//'is_active' => true,
			//'has_config' => true,
			//'can_be_disabled' => true,
			// 'role' => 'manage_options'
			)
		);
		$this->default_settings = array(
			'tracking_id' => '',
			'tracking_format' => 'none',
			'tacking_anonymize' => 0,
			'alfonso_content_injection_method' => 'manual'
			);
	}

	public function admin(){
		$this->read_settings();
		if (isset($_POST['machete-utils-saved'])){
  			check_admin_referer( 'machete_save_utils' );
			$this->save_settings( $_POST );
		}
		add_action( 'admin_menu', array(&$this, 'register_sub_menu') );
	}

	protected function save_settings( $settings = array(), $silent = false) {

		/*
		tracking_id
		tracking_format: standard, machete, none
		header_content
		footer_content
		*/

		$header_content = '';
		
		if (!is_dir(MACHETE_DATA_PATH)){
			if(!@mkdir(MACHETE_DATA_PATH)){
				if (!$silent) $this->notice( sprintf( __( 'Error creating data dir %s please check file permissions', 'machete' ), MACHETE_DATA_PATH), 'error');
				return false;
			}
		}

		if(!empty($settings['tracking_id'])){

			if(!preg_match('/^ua-\d{4,9}-\d{1,4}$/i', strval( $settings['tracking_id'] ))){
				// invalid Analytics Tracking ID
				// http://code.google.com/apis/analytics/docs/concepts/gaConceptsAccounts.html#webProperty
				if (!$silent) $this->notice( __( 'That doesn\'t look like a valid Google Analytics tracking ID', 'machete' ), 'warning');
				return false;
			}
			$new_settings['tracking_id'] = $settings['tracking_id'];

			if( !in_array( $settings['tracking_format'], array('standard','machete','none') )){
				// I don't know that tracking format
				if (!$silent) $this->notice( __( 'Something went wrong. Unknown tracking code format requested.', 'machete' ), 'warning');
				return false;
			}
			$new_settings['tracking_format'] = $settings['tracking_format'];

			if ( isset( $settings['tacking_anonymize'] )){
				$new_settings['tacking_anonymize'] = 1;
				$anonymizeIp = ',{anonymizeIp: true}';
			}else{
				$new_settings['tacking_anonymize'] = 0;
				$anonymizeIp = '';
			}

			// let's generate the Google Analytics tracking code
			if($new_settings['tracking_format'] == 'machete'){
				$header_content .= 'if (!navigator.userAgent || ('."\n";
				$header_content .= '  (navigator.userAgent.indexOf("Speed Insights") == -1) &&'."\n";
				$header_content .= '  (navigator.userAgent.indexOf("Googlebot") == -1)'."\n";
				$header_content .= ')) {'."\n";
			}
			if($new_settings['tracking_format'] != 'none'){
				
				$header_content .= '(function(i,s,o,g,r,a,m){i[\'GoogleAnalyticsObject\']=r;i[r]=i[r]||function(){'."\n";
				$header_content .= '(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),'."\n";
				$header_content .= 'm=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)'."\n";
				$header_content .= '})(window,document,\'script\',\'https://www.google-analytics.com/analytics.js\',\'ga\');'."\n";

				$header_content .= 'ga(\'create\', \''.$settings['tracking_id'].'\', \'auto\''.$anonymizeIp.');'."\n";
				$header_content .= 'ga(\'send\', \'pageview\');'."\n";
				
			}
			if($new_settings['tracking_format'] == 'machete'){
				$header_content .= '}'."\n";
			}
			if($new_settings['tracking_format'] != 'none'){
				$header_content = "<script>\n".$header_content."</script>\n<!-- Machete Header -->\n";
			}
		}else{
			$new_settings['tracking_id'] = '';
			$new_settings['tracking_format'] = 'none';
		}

		if(!empty($settings['header_content'])){
			$header_content .= stripslashes(wptexturize($settings['header_content']));
		}

		if(!empty($header_content)){
			file_put_contents(MACHETE_DATA_PATH.'header.html', $header_content, LOCK_EX);
		}else{
			if (file_exists(MACHETE_DATA_PATH.'header.html')){
				unlink(MACHETE_DATA_PATH.'header.html');
			}
		}


		if(!empty($settings['alfonso_content_injection_method']) &&
			($this->settings['alfonso_content_injection_method'] == 'auto')){
			$new_settings['alfonso_content_injection_method'] = 'auto';
		}else{
			$new_settings['alfonso_content_injection_method'] = 'manual';
		}


		if(!empty($settings['alfonso_content'])){
			$alfonso_content = stripslashes(wptexturize($settings['alfonso_content']));
			file_put_contents(MACHETE_DATA_PATH.'body.html', $alfonso_content, LOCK_EX);
		}else{
			if (file_exists(MACHETE_DATA_PATH.'body.html')){
				unlink(MACHETE_DATA_PATH.'body.html');
			}
		}


		if(!empty($settings['footer_content'])){
			$footer_content = stripslashes(wptexturize($settings['footer_content']));
			file_put_contents(MACHETE_DATA_PATH.'footer.html', $footer_content, LOCK_EX);
		}else{
			if (file_exists(MACHETE_DATA_PATH.'footer.html')){
				unlink(MACHETE_DATA_PATH.'footer.html');
			}
		}
		
		if(
			(0 == count(array_diff($new_settings, $this->settings))) &&
			(0 == count(array_diff($this->settings, $new_settings)))
			){
			// no removes && no adds
			// ToDo: check for changes in the other sections
			//       give "no changes" notice only if no changes at all
			//if (!$silent) $this->notice(__( 'No changes were needed.', 'machete' ), 'info');
			return true;
		}
		


		// option saved WITHOUT autoload
		if(update_option( 'machete_utils_settings', $new_settings, 'no' )){
			$this->settings = $new_settings;
			if (!$silent) $this->save_success_notice();
			return true;
		}else{
			if (!$silent) $this->save_error_notice();
			return false;
		}

	}

	protected function import( $settings = array() ){

		$encoded_fields = array('header_content', 'alfonso_content', 'footer_content');

		foreach ($encoded_fields as $encoded_field){
			if (array_key_exists($encoded_field, $settings) && ! empty($settings[$encoded_field]) ){
				$settings[$encoded_field] = base64_decode($settings[$encoded_field]);
			}
		}
			
		if ( $this->save_settings($settings, true) ){
			return __('Settings succesfully restored from backup', 'machete') . "\n";
		}else{
			return __('Error restoring settings backup', 'machete') . "\n";
		}
	}

	protected function export(){

		$export = $this->settings;

		if($machete_header_content = @file_get_contents(MACHETE_DATA_PATH.'header.html')){

			$machete_header_content = explode('<!-- Machete Header -->', $machete_header_content);
			switch(count($machete_header_content)){
				case 1:
					$machete_header_content = $machete_header_content[0];
					break;
				case 2:
					$machete_header_content = $machete_header_content[1];
					break;
				default:
					$machete_header_content = implode('',array_slice($machete_header_content, 1));
			}
			$export['header_content'] = base64_encode($machete_header_content);
		}
		if (file_exists(MACHETE_DATA_PATH.'body.html')){
			$export['alfonso_content'] = base64_encode(file_get_contents(MACHETE_DATA_PATH.'body.html'));
		}

		if (file_exists(MACHETE_DATA_PATH.'footer.html')){
			$export['footer_content'] = base64_encode(file_get_contents(MACHETE_DATA_PATH.'footer.html'));
		}

		return $export;
	}

}
$machete->modules['utils'] = new machete_utils_module();