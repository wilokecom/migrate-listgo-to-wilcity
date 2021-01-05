<?php
/*
 * Plugin Name: Wiloke - Migrate From ListGo To Wilcity
 * Plugin URI: https://wiloke.net
 * Author: Wiloke
 * Author URI: https://wiloke.net
 * Description: It helps to migrate from ListGo to Wilcity
 */

use WilokeListingTools\MetaBoxes\Listing as ListingMetaBox;

function wilcityImportVerifyNonce()
{
	$status = check_ajax_referer('wilcity_nonce_action', 'nonce', false);
	if (!$status) {
		wp_send_json_success(array('msg' => 'Invalid Nonce'));
	}
}

function wilcityImportListgoTermsOptions()
{
	wilcityImportVerifyNonce();
}

function wilcityImportListgoCustomFields()
{
	wilcityImportVerifyNonce();

	if (current_user_can('administrator')) {
		$aCustomFieldData = [];
		if (!empty($_POST['data'])) {
			$aCustomFieldData = $_POST['data'];
		}
		if (!empty($aCustomFieldData)) {
			if (is_string($aCustomFieldData)) {
				$aParseData = unserialize(base64_decode($aCustomFieldData));
			} else if (is_array($aCustomFieldData)) {
				$aParseData = unserialize(base64_decode($aCustomFieldData)['custom_field']);
			}

			if (!empty($aParseData)) {
				foreach ($aParseData as $slug => $aCustomFields) {
					$postID = wilokeImportFindPostIDBySlug($slug);
					if (empty($postID)) {
						continue;
					}
					$postID = abs($postID);
					foreach ($aCustomFields as $fieldKey => $data) {
						\WilokeListingTools\Framework\Helpers\SetSettings::setPostMeta($postID, 'custom_' .
							$fieldKey, $data);
					}
				}
			}

			if (is_array($aCustomFieldData) && !empty($aCustomFieldData['business_hour'])) {
				$aParseBusinessHourData = unserialize(base64_decode($aCustomFieldData['business_hour']));
				if (!empty($aParseBusinessHourData)) {

					foreach ($aParseBusinessHourData as $slug => $aDaysOfWeek) {
						$postID = wilokeImportFindPostIDBySlug($slug);
						if (empty($postID)) {
							continue;
						}
						$postID = abs($postID);
						$timezone = get_option('timezone_string');
						$aData['timeFormat'] = 'inherit';
						$aData['hourMode'] = 'open_for_selected_hours';
						$aBusinessHour = [];
						foreach ($aDaysOfWeek as $fieldKey => $data) {
							switch ($fieldKey) {
								case 0:
									$day = 'sunday';
									break;
								case 1:
									$day = 'monday';
									break;
								case 2:
									$day = 'tuesday';
									break;
								case 3:
									$day = 'wednesday';
									break;
								case 4:
									$day = 'thursday';
									break;
								case 5:
									$day = 'friday';
									break;
								case 6:
									$day = 'saturday';
									break;
								default:
									$day = '';
									break;
							}

							$aBusinessHour[$day]['operating_times'] = [
								'firstOpenHour'  => $data['open_time'],
								'firstCloseHour' => $data['close_time'],
							];
						}
						$aData['businessHours'] = $aBusinessHour;

						\WilokeListingTools\MetaBoxes\Listing::saveBusinessHours($postID, $aData, $timezone);
					}
				}

			}
		}
		wp_send_json_success(array('msg' => 'Congratulations! The custom fields have been imported successfully'));
	} else {
		wp_send_json_error(array('msg' => 'You do not have permission to access this page.'));
	}
}

function wilokeImportFindPostIDBySlug($slug)
{
	global $wpdb;
	$postID = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM $wpdb->posts WHERE post_name=%s ORDER BY $wpdb->posts.ID DESC",
			$slug
		)
	);
	return $postID;
}

function wilcityImportEventFields()
{
	wilcityImportVerifyNonce();
	if (current_user_can('administrator')) {
		if (!empty($_POST['data'])) {
			$aParseData = unserialize(base64_decode($_POST['data']));

			if (!empty($aParseData)) {
				foreach ($aParseData as $slug => $aEventData) {
					$postID = wilokeImportFindPostIDBySlug($slug);
					if (empty($postID)) {
						continue;
					}
					$postID = abs($postID);

					foreach ($aEventData as $field => $aEventDetailData) {
						if ($field == 'geocode') {
							if (!empty($aEventDetailData['latLng'])) {
								$aLatLng = explode(',', $aEventDetailData['latLng']);
								ListingMetaBox::saveData($postID, array(
									'lat'     => trim($aLatLng[0]),
									'lng'     => trim($aLatLng[1]),
									'address' => $aEventDetailData['place']
								));
							}
						} else {
							$timeZone = get_option('timezone_string');
							if (!empty($timeZone) && strpos($timeZone, 'UTC') === false) {
								$aEventData
									= array('objectID' => $postID, 'frequency' => 'occurs_once', 'timezone' => $timeZone);
								$aPrepares = array('%d', '%s', '%s');
								if (!empty($aEventDetailData)) {
									$parentID = wilokeImportFindPostIDBySlug($aEventDetailData['parentSlug']);
									if (!empty($parentID)) {
										wp_update_post(
											array(
												'ID'          => $postID,
												'post_parent' => $parentID
											)
										);
									}

									$aEventGeneralSettings = $aEventDetailData['data'];

									if (!empty($aEventGeneralSettings['start_at'])) {
										$aEventData['openingAt'] = $aEventGeneralSettings['start_at'];
										$aPrepares[] = '%s';
									}
									$aEventData['starts'] = $aEventGeneralSettings['start_on'];
									$aPrepares[] = '%s';

									if (!empty($aEventGeneralSettings['end_at'])) {
										$aEventData['closedAt'] = $aEventGeneralSettings['end_at'];
										$aPrepares[] = '%s';
									}

									$aEventData['endsOn'] = $aEventGeneralSettings['end_on'];
									$aPrepares[] = '%s';
									\WilokeListingTools\Models\EventModel::updateEventData('', array(
										'values'   => $aEventData,
										'prepares' => $aPrepares
									));
								}
							}
						}
					}
				}
			}
		}
		wp_send_json_success(array('msg' => 'Congratulations! The custom fields have been imported successfully'));
	} else {
		wp_send_json_error(array('msg' => 'You do not have permission to access this page.'));
	}
}

add_action('wp_ajax_wilcity_import_listgo_custom_fields', 'wilcityImportListgoCustomFields');

add_action('wp_ajax_wilcity_import_listgo_terms_options', 'wilcityImportListgoTermsOptions');
add_action('wp_ajax_wilcity_import_listgo_event_fields', 'wilcityImportEventFields');

function wilokeImportListgoDataArea()
{
	?>
    <div class="ui segment">
        <form id="wilcity-import-listgo-terms-options"
              action="<?php echo admin_url('admin.php?page=export-listgo-data'); ?>" method="POST"
              class="form ui wilcity-import-listgo" data-ajax="wilcity_import_listgo_terms_options">
            <div class="field">
                <label for="terms-options-data">Terms Options Data</label>
                <textarea name="terms_options_data" id="terms-options-data" class="data" cols="30" rows="10"></textarea>
            </div>
			<?php echo wp_nonce_field('wilcity_nonce_action', 'wilcity_nonce_fields'); ?>
            <input type="hidden" name="run_export_terms_options" value="1">
            <div class="field">
                <button class="ui button green">Import Terms Options</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeImportListgoCustomFields()
{
	?>
    <div class="ui segment">
        <form id="wilcity-import-listgo-custom-fields"
              action="<?php echo admin_url('admin.php?page=import-listgo-custom-fields'); ?>" method="POST"
              class="form ui wilcity-import-listgo" data-ajax="wilcity_import_listgo_custom_fields">
            <div class="field">
                <label for="listgo_custom_field_data">Paste Listgo Custom Fields Data To The Field below</label>
                <textarea name="listgo_custom_field_data" id="listgo_custom_field_data" class="data" cols="30"
                          rows="10"></textarea>
            </div>
            <div class="field">
                <label for="listgo_business_hours_field_data">Paste Listgo Business Hour Fields Data To The Field
                    below</label>
                <textarea name="listgo_business_hours_field_data" id="listgo_business_hours_field_data" class="data"
                          cols="30" rows="10"></textarea>
            </div>
			<?php echo wp_nonce_field('wilcity_nonce_action', 'wilcity_nonce_fields'); ?>
            <input type="hidden" name="run_import_custom_fields" value="1">
            <div class="field">
                <button type="submit" class="ui button green">Import Now</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeImportListgoEventsFields()
{
	?>
    <div class="ui segment">
        <form id="wilcity-import-event-fields"
              action="<?php echo admin_url('admin.php?page=import-listgo-event-fields'); ?>" method="POST"
              class="form ui wilcity-import-listgo" data-ajax="wilcity_import_listgo_event_fields">
            <div class="field">
                <label for="listgo_custom_field_data">Paste Events Data To The Field below</label>
                <textarea name="listgo_custom_field_data" id="listgo_custom_field_data" class="data" cols="30"
                          rows="10"></textarea>
            </div>
			<?php echo wp_nonce_field('wilcity_nonce_action', 'wilcity_nonce_fields'); ?>
            <input type="hidden" name="run_import_custom_fields" value="1">
            <div class="field">
                <button type="submit" class="ui button green">Import Now</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeImportListgoData()
{
	add_menu_page('Import Listgo Data', 'Import Listgo Data', 'manage_options', 'import-listgo-data', 'wilokeImportListgoDataArea');
	add_menu_page('Import Listgo Listings', 'Import Listgo Listings', 'manage_options', 'import-listgo-custom-fields', 'wilokeImportListgoCustomFields');
	add_menu_page('Import Listgo Events', 'Import Listgo Events', 'manage_options', 'import-listgo-event-fields', 'wilokeImportListgoEventsFields');
}

function wilcityImportListgoScripts()
{
	wp_enqueue_style('semantic', plugin_dir_url(__FILE__) . 'vendor/semantic/semantic.css');
	wp_enqueue_script('wilcity-import-listgo', plugin_dir_url(__FILE__) .
		'source/js/script.js', array('jquery'), 1.3, true);
}

add_action('admin_menu', 'wilokeImportListgoData');
add_action('admin_enqueue_scripts', 'wilcityImportListgoScripts');

include_once plugin_dir_path(__FILE__) . 'wiloke-listgo-migration.php';