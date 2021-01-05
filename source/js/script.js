(function ($) {
	'use strict';

	$(document).ready(function () {
		$('.wilcity-import-listgo').on('submit', function (event) {
			event.preventDefault();
			var $this = $(this);
			let data;
			$this.addClass('loading');
			if ($this.data('ajax') === 'wilcity_import_listgo_custom_fields') {
				data = {
					'custom_field': $this.find('.data').eq(0).val(),
					'business_hour': $this.find('.data').eq(1).val(),
				};
			}
			else{
				data =$this.find('.data').val()
			}
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: $this.data('ajax'),
					nonce: $('#wilcity_nonce_fields').val(),
					data: data
				},
				success: function (response) {
					if ( response.success ){
						alert(response.data.msg);
					}
					$this.removeClass('loading');
				}
			})
		})
	})

})(jQuery);