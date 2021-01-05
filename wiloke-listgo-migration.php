<?php
use WilokeListingTools\MetaBoxes\Listing as ListingMetaBox;
use WilokeListingTools\Framework\Helpers\SetSettings;
use WilokeListingTools\Framework\Helpers\GetSettings;

include plugin_dir_path(__FILE__) . 'export/wiloke-listgo-export.php';
include plugin_dir_path(__FILE__) . 'rapid-addon.php';

$listgoAddon = new RapidAddon('Migrating Listgo To Wilcity', 'listgo_listings_to_wilcity');

//$listgoAddon->add_field('wiloke_listgo_logo', 'Logo', 'text');
$listgoAddon->add_field('wiloke_listgo_settings', 'Listing General Settings', 'text');
$listgoAddon->add_field('wiloke_listgo_toggle_business_hours', 'Toggle Business Hours', 'text');
$listgoAddon->add_field('wiloke_listgo_business_hours', 'Business Hours', 'text');
$listgoAddon->add_field('wiloke_listgo_listing_claim', 'Listing Claim Block', 'text');
$listgoAddon->add_field('wiloke_listgo_price_segment', 'Listing Price (Price Range)', 'text');
$listgoAddon->add_field('wiloke_listgo_social_media', 'Social Media Block', 'text');
$listgoAddon->add_field('wiloke_listgo_featured_image', 'Featured Image', 'text');
$listgoAddon->add_field('wiloke_listgo_gallery', 'Gallery', 'text');


function wilcityMigrationInsertImage($imgSrc){
	$wp_upload_dir = wp_upload_dir();
	$aParseImgSrc = explode('/', $imgSrc);
	$filename = end($aParseImgSrc);
	$filetype = wp_check_filetype( $filename, null );
	if ( is_file($wp_upload_dir['path'] . '/' . $filename) ){
		global $wpdb;
		$postTitle = preg_replace( '/\.[^.]+$/', '', $filename );

		$postID = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_title=%s and post_mime_type=%s",
				$postTitle, $filetype['type']
			)
		);

		return array(
			'id'    => $postID,
			'url'   => $wp_upload_dir['url'] . '/' . $filename
		);
	}

	$ch = curl_init ($imgSrc);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
	$raw=curl_exec($ch);
	curl_close ($ch);
	$fp = fopen($wp_upload_dir['path'] . '/' . $filename,'x');
	$writeStatus = fwrite($fp, $raw);
	fclose($fp);
	if ( $writeStatus === false ){
		return false;
	}

	// Get the path to the upload directory.
	// Prepare an array of post data for the attachment.
	$attachment = array(
		'post_mime_type' => $filetype['type'],
		'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
		'post_content'   => '',
		'post_status'    => 'inherit'
	);

	// Insert the attachment.
	$attach_id = wp_insert_attachment( $attachment, $wp_upload_dir['path'] . '/' . $filename);

	// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
	require_once( ABSPATH . 'wp-admin/includes/image.php' );

	$imagenew = get_post( $attach_id );
	if ( empty($imagenew) ){
		return array(
			'id' => $attach_id,
			'url' => $wp_upload_dir['url'] . '/' . $filename
		);
	}
	$fullsizepath = get_attached_file( $imagenew->ID );
	$attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
	wp_update_attachment_metadata( $attach_id, $attach_data );

	return array(
		'id' => $attach_id,
		'url' => $wp_upload_dir['url'] . '/' . $filename
	);
}

function wilokeCorrectBH($val){
	return strlen($val) == 1 ? '0' . $val : $val;
}

function wilokeValidMin($min){
	$aValidMin = array('00', '15', '30', '45', '30', '45');
	if ( in_array($min, $aValidMin) ){
		return $min;
	}

	$min = abs($min);

	if ( $min > 45 ){
		return 45;
	}

	foreach ($aValidMin as $order => $number){
		if ( $min < $aValidMin[$order+1] ){
			return $number;
		}
	}
}

function wilokeBuildBH($hour, $min, $format){
	$time = $hour . ':' . wilokeValidMin($min) . ' ' . $format;
	return date('H:i:s', strtotime($time));
}

function wiloke_migrate_from_listgo_to_wilcity($postID, $aData, $importOptions, $aListing){
	global $listgoAddon;

	$aFields = array(
		'wiloke_listgo_logo',
		'wiloke_listgo_settings',
		'wiloke_listgo_price_segment',
		'wiloke_listgo_social_media',
		'wiloke_listgo_toggle_business_hours',
		'wiloke_listgo_business_hours',
		'wiloke_listgo_listing_claim',
		'wiloke_listgo_featured_image',
		'wiloke_listgo_gallery',
		'wiloke_listgo_custom_fields'
	);

	$aDaysOfWeeks = wilokeListingToolsRepository()->get('general:aDayOfWeek');
	$aDaysOfWeekKeys = array_keys($aDaysOfWeeks);

	$aBusinessHours = array();
	foreach ( $aFields as $field ) {
		if ( empty( $aListing['ID'] ) || $listgoAddon->can_update_meta( $field, $importOptions ) ) {
			$data = $aData[$field];

			switch ($field){
				case 'wiloke_listgo_settings':
					$aParseData = maybe_unserialize($data);
					if ( !empty($aParseData) ){
						if ( isset($aParseData['map']) && !empty($aParseData['map']) ){
							$aLatLng = explode(',', $aParseData['map']['latlong']);
							ListingMetaBox::saveData($postID, array(
								'lat'        => trim($aLatLng[0]),
								'lng'        => trim($aLatLng[1]),
								'address'    => $aParseData['map']['location']
							));
						}

						if ( isset($aParseData['phone_number']) ){
							SetSettings::setPostMeta($postID, 'phone', $aParseData['phone_number']);
						}

						if ( isset($aParseData['website']) ){
							SetSettings::setPostMeta($postID, 'website', $aParseData['website']);
						}

						if ( isset($aParseData['contact_email']) ){
							SetSettings::setPostMeta($postID, 'email', $aParseData['contact_email']);
						}

						if ( isset($aParseData['logo']) && !empty($aParseData['logo']) ){
							$aLogo = wilcityMigrationInsertImage($aParseData['logo']);
							if ( $aLogo ){
								SetSettings::setPostMeta($postID, 'logo', $aLogo['url']);
								SetSettings::setPostMeta($postID, 'logo_id', $aLogo['id']);
							}
						}
					}
					break;
				case 'wiloke_listgo_price_segment':
					$aParseData = maybe_unserialize($data);
					if ( !empty($aParseData) ){
						SetSettings::setPostMeta($postID, 'price_range', $aParseData['price_segment']);
						SetSettings::setPostMeta($postID, 'minimum_price', $aParseData['price_from']);
						SetSettings::setPostMeta($postID, 'maximum_price', $aParseData['price_to']);
					}
					break;
				case 'wiloke_listgo_social_media':
					$aParseData = maybe_unserialize($data);
					if ( !empty($aParseData) ){
						SetSettings::setPostMeta($postID, 'social_networks', $aParseData);
					}
					break;
				case 'wiloke_listgo_toggle_business_hours':
					if ( $data == 'disable' ){
						$aBusinessHours['hourMode'] = 'no_hours_available';
					}else{
						$aBusinessHours['hourMode'] = 'open_for_selected_hours';
					}
					break;
				case 'wiloke_listgo_business_hours':
					$aParseData = maybe_unserialize($data);
					if ( empty($aParseData) ){
						$aBusinessHours['hourMode'] = 'no_hours_available';
					}else{
						$aBusinessHours['hourMode'] = 'open_for_selected_hours';
						$aBusinessHours['businessHours'] = array();

						$aDay = array();
						foreach ($aParseData as $order => $aItem){
							if ( !isset($aItem['closed']) || $aItem['closed'] == '1' ){
								$aDay['isOpen'] = 'yes';
							}else{
								$aDay['isOpen'] = 'no';
							}

							$firstOpenHour = wilokeBuildBH($aItem['start_hour'], $aItem['start_minutes'], $aItem['start_format']);
							$firstCloseHour = wilokeBuildBH($aItem['close_hour'], $aItem['close_minutes'], $aItem['close_format']);
							if ( !$firstOpenHour || !$firstCloseHour ){
								$aDay['operating_times']['firstOpenHour'] = '';
								$aDay['operating_times']['firstCloseHour'] = '';
							}else{
								$aDay['operating_times']['firstOpenHour'] = $firstOpenHour;
								$aDay['operating_times']['firstCloseHour'] = $firstCloseHour;

								if ( isset($aItem['extra_open_hour']) && isset($aItem['extra_open_minutes']) && isset($aItem['extra_close_hour']) && isset($aItem['extra_close_minutes']) ){
									$secondOpenHour = wilokeBuildBH($aItem['extra_open_hour'], $aItem['extra_open_minutes'], $aItem['extra_open_format']);
									$secondCloseHour = wilokeBuildBH($aItem['extra_close_hour'], $aItem['extra_close_minutes'], $aItem['extra_close_format']);
									$aDay['operating_times']['secondOpenHour']  = $secondOpenHour;
									$aDay['operating_times']['secondCloseHour'] = $secondCloseHour;
								}
							}

							$aBusinessHours['businessHours'][$aDaysOfWeekKeys[$order]] = $aDay;
						}
					}
					ListingMetaBox::saveBusinessHours($postID, $aBusinessHours);
					break;
				case 'wiloke_listgo_listing_claim':
					$aParseData = maybe_unserialize($data);
					if ( empty($aParseData) ){
						SetSettings::setPostMeta($postID, 'claim_status', 'claimed');
					}else{
						if ( $aParseData['status'] == 'not_claimed' ){
							$aParseData['status'] = 'not_claim';
						}
						SetSettings::setPostMeta($postID, 'claim_status', $aParseData['status']);
					}
					break;
				case 'wiloke_listgo_featured_image':
					$aAttachment = wilcityMigrationInsertImage($data);
					if ( $aAttachment ){
						set_post_thumbnail($postID, $aAttachment['id']);
					}
					break;
				case 'wiloke_listgo_gallery':
					$aParseData = maybe_unserialize($data);
					if ( !empty($aParseData) && isset($aParseData['gallery']) && !empty($aParseData['gallery']) ){
						$aDownloadedGallery = array();
						foreach ($aParseData['gallery'] as $imgSrc){
							$aAttachment = wilcityMigrationInsertImage($imgSrc);
							if ( $aAttachment ){
								$aDownloadedGallery[$aAttachment['id']] = $aAttachment['url'];
							}
						}
						if ( !empty($aDownloadedGallery) ){
							SetSettings::setPostMeta($postID, 'gallery', $aDownloadedGallery);
						}
					}
					break;
			}
		}
	}
}

$listgoAddon->set_import_function('wiloke_migrate_from_listgo_to_wilcity');

$listgoAddon->run(
	array(
		'themes'  => array('WilCity')
	)
);

//$eventAddon = new RapidAddon('Migrating Listgo Events', 'listgo_events_to_wilcity');
//$eventAddon->add_field('wiloke_listgo_event_settings', 'Listing Event General Settings', 'text');
//
//function wiloke_migrate_from_listgo_event_to_wilcity($postID, $aData, $importOptions, $aListing){
//	global $listgoAddon;
//
//	$aFields = array(
//		'wiloke_listgo_event_settings'
//	);
//
//	foreach ( $aFields as $field ) {
//		if ( empty( $aListing['ID'] ) || $listgoAddon->can_update_meta( $field, $importOptions ) ) {
//			$data = $aData[$field];
//
//			switch ($field){
//				case 'wiloke_listgo_event_settings':
//					$aParseData = maybe_unserialize($data);
//					if ( !empty($aParseData) ){
//						if ( isset($aParseData['map']) && !empty($aParseData['map']) ){
//							$aLatLng = explode(',', $aParseData['map']['latlong']);
//							ListingMetaBox::saveData($postID, array(
//								'lat'        => trim($aLatLng[0]),
//								'lng'        => trim($aLatLng[1]),
//								'address'    => $aParseData['map']['location']
//							));
//						}
//
//						if ( isset($aParseData['phone_number']) ){
//							SetSettings::setPostMeta($postID, 'phone', $aParseData['phone_number']);
//						}
//
//						if ( isset($aParseData['website']) ){
//							SetSettings::setPostMeta($postID, 'website', $aParseData['website']);
//						}
//
//						if ( isset($aParseData['contact_email']) ){
//							SetSettings::setPostMeta($postID, 'email', $aParseData['contact_email']);
//						}
//
//						if ( isset($aParseData['logo']) && !empty($aParseData['logo']) ){
//							$aLogo = wilcityMigrationInsertImage($aParseData['logo']);
//							if ( $aLogo ){
//								SetSettings::setPostMeta($postID, 'logo', $aLogo['url']);
//								SetSettings::setPostMeta($postID, 'logo_id', $aLogo['id']);
//							}
//						}
//					}
//					break;
//			}
//		}
//	}
//}
//$eventAddon->set_import_function('wiloke_migrate_from_listgo_event_to_wilcity');
//$eventAddon->run(
//	array(
//		'themes'  => array('WilCity')
//	)
//);