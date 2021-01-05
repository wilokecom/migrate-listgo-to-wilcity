<?php

use WilokeListgoFunctionality\AlterTable\AlterTableGeoPosition;

function wilokeExportListgoDataArea()
{
	$aTaxonomies = array('listing_location', 'listing_cat', 'listing_tag');
	$aTaxonomiesOptions = array();
	if (isset($_POST['run_export_terms_options']) && !empty($_POST['run_export_terms_options'])) {
		foreach ($aTaxonomies as $taxonomy) {
			$aTerms = get_terms(array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true
			));

			if (empty($aTerms) || is_wp_error($aTerms)) {
				continue;
			}

			$aTaxonomiesOptions[$taxonomy] = array();
			foreach ($aTerms as $oTerm) {
				$aTermOptions = get_option('_wiloke_cat_settings_' . $oTerm->term_id);
				$aTaxonomies[$taxonomy][$oTerm->slug] = maybe_serialize($aTermOptions);
			}
		}
	}
	?>
    <div class="ui segment">
        <form action="<?php echo admin_url('admin.php?page=export-listgo-data'); ?>" method="POST" class="form ui">
            <div class="field">
                <label for="terms-options-data">Terms Options Data</label>
                <textarea name="terms_options_data" id="terms-options-data" cols="30"
                          rows="10"><?php echo maybe_serialize($aTaxonomies); ?></textarea>
            </div>

            <input type="hidden" name="run_export_terms_options" value="1">
            <div class="field">
                <button class="ui button green">Export Terms Options</button>
            </div>
        </form>
    </div>
	<?php
}

//function wilokeExportListgoBusinessHours(){
//
//}
function wilokeExportListgoListingCustomFields()
{
	$aFields = array();
	$aBusinessHourFields = [];
	if (isset($_POST['run_export_custom_fields']) && !empty($_POST['run_export_custom_fields'])) {
		$query = new WP_Query(
			array(
				'post_type'      => 'listing',
				'posts_per_page' => absint($_POST['listings_per_export']),
				'paged'          => abs($_POST['listing_page'])
			)
		);

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$data = get_post_meta($query->post->ID, 'wiloke_listgo_my_custom_fields', true);
				$aBusinessHourData
					= \WilokeListgoFunctionality\Framework\Helpers\GetSettings::getBusinessHours($query->post->ID);
				if (empty($data)) {
					$aFields[$query->post->post_name] = '';
				} else {
					$aFields[$query->post->post_name] = $data;
				}

				if (empty($aBusinessHourData)) {
					$aBusinessHourFields[$query->post->post_name] = '';
				} else {
					$aBusinessHourFields[$query->post->post_name] = $aBusinessHourData;
				}
			}
		}
	}

	?>
    <div class="ui segment">
        <h1 class="ui heading dividing">Export Listing Custom Fields</h1>

        <form action="<?php echo admin_url('admin.php?page=export-listgo-custom-fields'); ?>" method="POST"
              class="form ui">
			<?php if (!empty($aFields)) : ?>
                <div class="field">
                    <label for="terms-options-data">Copy This Data and Parse To Wiloke Listgo Import -> Import Custom
                        Fields</label>
                    <textarea cols="30" rows="10"><?php echo base64_encode(serialize($aFields)); ?></textarea>
                </div>

			<?php endif; ?>
	        <?php if (!empty($aBusinessHourFields)) : ?>
            <div class="field">
                <label for="terms-options-data">Copy This Business Hour Data and Parse To Wiloke Listgo Import ->
                    Import Business Hour
                    Fields</label>
                <textarea cols="30"
                          rows="10"><?php echo base64_encode(serialize($aBusinessHourFields)); ?></textarea>
            </div>
	        <?php endif; ?>
            <div class="field">
                <label for="listings-per-export">Maximum Listings / Export</label>
                <input id="listings-per-export" type="text" name="listings_per_export" value="100">
            </div>
            <div class="field">
                <label for="terms-options-data">Current Page</label>
                <p>Assume we wish to export all listings from 1 - 30 (inclusive). You should enter Maximum Listings = 30
                    and Current Page. Current page = 2 means it will export start on 31.</p>
                <input id="listings-page" type="text" name="listing_page" value="1">
            </div>

            <input type="hidden" name="run_export_custom_fields" value="1">
            <div class="field">
                <button class="ui button green">Export Custom Fields</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeListGoGetGeocode($postID)
{
	global $wpdb;
	$tableName = $wpdb->prefix . AlterTableGeoPosition::$tblName;

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $tableName WHERE postID=%d",
			$postID
		),
		ARRAY_A
	);
}

function wilokeExportListgoEventFields()
{
	$aFields = array();
	if (isset($_POST['run_export_event_fields']) && !empty($_POST['run_export_event_fields'])) {
		$query = new WP_Query(
			array(
				'post_type'      => 'event',
				'posts_per_page' => absint($_POST['listings_per_export']),
				'paged'          => abs($_POST['listing_page'])
			)
		);

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$aData = get_post_meta($query->post->ID, 'event_settings', true);
				$place = '';

				if (empty($aData)) {
					$aFields[$query->post->post_name]['event_settings'] = '';
				} else {
					$aFields[$query->post->post_name]['event_settings']['data'] = $aData;
					$aFields[$query->post->post_name]['event_settings']['parentSlug'] = isset($aData['belongs_to']) &&
					!empty($aData['belongs_to']) ? get_post_field('post_name', $aData['belongs_to']) : '';
					$place = $aData['place_detail'];

					$aFields[$query->post->post_name]['geocode']['latLng'] = $aData['latitude'] . ',' .
						$aData['longitude'];
					$aFields[$query->post->post_name]['geocode']['place'] = $place;
				}
			}
		}
	}

	?>
    <div class="ui segment">
        <h1 class="ui heading dividing">Export Event Data</h1>

        <form action="<?php echo admin_url('admin.php?page=export-listgo-event-fields'); ?>" method="POST"
              class="form ui">
	        <?php if (!empty($aFields)) : ?>
                <div class="field">
                    <label for="event-data">Copy This Data and Parse To Wiloke Listgo Import -> Import Custom
                        Fields</label>
                    <textarea id="event-data" cols="30"
                              rows="10"><?php echo base64_encode(serialize($aFields)); ?></textarea>
                </div>
	        <?php endif; ?>

            <div class="field">
                <label for="listings-per-export">Maximum Listings / Export</label>
                <input id="listings-per-export" type="text" name="listings_per_export" value="100">
            </div>
            <div class="field">
                <label for="terms-options-data">Current Page</label>
                <p>Assume we wish to export all listings from 1 - 30 (inclusive). You should enter Maximum Listings = 30
                    and Current Page. Current page = 2 means it will export start on 31.</p>
                <input id="listings-page" type="text" name="listing_page" value="1">
            </div>

            <input type="hidden" name="run_export_event_fields" value="1">
            <div class="field">
                <button class="ui button green">Export Custom Fields</button>
            </div>
        </form>
    </div>
	<?php
}


function wilokeExportListgoData()
{
//	add_menu_page( 'Export Listgo Data', 'Export Listgo Data', 'manage_options', 'export-listgo-data', 'wilokeExportListgoDataArea' );
	add_menu_page('Export Listgo Listings', 'Export Listgo Listings', 'manage_options', 'export-listgo-custom-fields', 'wilokeExportListgoListingCustomFields');
	add_menu_page('Export Listgo Events', 'Export Listgo Events', 'manage_options', 'export-listgo-event-fields', 'wilokeExportListgoEventFields');
}

function wilcityExportListgoScripts()
{
	wp_enqueue_style('semantic', plugin_dir_url(__FILE__) . './../vendor/semantic/semantic.css');
}

add_action('admin_menu', 'wilokeExportListgoData');
add_action('admin_enqueue_scripts', 'wilcityExportListgoScripts');