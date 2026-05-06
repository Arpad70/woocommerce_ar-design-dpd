<?php

$type = isset($type) ? (string) $type : '';
$parcelshop_id = isset($parcelshop_id) ? (string) $parcelshop_id : '';
$parcelshop_pus_id = isset($parcelshop_pus_id) ? (string) $parcelshop_pus_id : '';
$parcelshop_name = isset($parcelshop_name) ? (string) $parcelshop_name : '';
$parcelshop_street = isset($parcelshop_street) ? (string) $parcelshop_street : '';
$parcelshop_zip = isset($parcelshop_zip) ? (string) $parcelshop_zip : '';
$parcelshop_city = isset($parcelshop_city) ? (string) $parcelshop_city : '';
$parcelshop_country_name = isset($parcelshop_country_name) ? (string) $parcelshop_country_name : '';
$parcelshop_country_code = isset($parcelshop_country_code) ? (string) $parcelshop_country_code : '';

?>

<div class="wc-dpd-chosen-parcelshop" <?php echo $type == 'admin' ? ' style="width: 100%; display: block;"' : ''; ?>>
	<p>
		<strong><?php echo __('Chosen Parcelshop', 'wc-dpd'); ?></strong>:<br>

		<?php if ($type == 'admin') : ?>
			<?php if ($parcelshop_id) : ?>
				<strong><?php echo __('Parcelshop ID', 'wc-dpd'); ?></strong>: <?php echo esc_html($parcelshop_id); ?><br>
			<?php endif; ?>

			<?php if ($parcelshop_pus_id) : ?>
				<strong><?php echo __('Parcelshop PUS ID', 'wc-dpd'); ?></strong>: <?php echo esc_html($parcelshop_pus_id); ?><br>
			<?php endif; ?>

			<?php if ($parcelshop_name) : ?>
				<strong><?php echo __('Parcelshop name', 'wc-dpd'); ?></strong>: <?php echo esc_html($parcelshop_name); ?><br>
			<?php endif; ?>

			<?php if ($parcelshop_street) : ?>
				<strong><?php echo __('Parcelshop street', 'wc-dpd'); ?></strong>: <?php echo esc_html($parcelshop_street); ?><br>
			<?php endif; ?>

			<?php if ($parcelshop_zip) : ?>
				<strong><?php echo __('Parcelshop zip', 'wc-dpd'); ?></strong>: <?php echo esc_html($parcelshop_zip); ?><br>
			<?php endif; ?>

			<?php if ($parcelshop_city) : ?>
				<strong><?php echo __('Parcelshop city', 'wc-dpd'); ?></strong>: <?php echo esc_html($parcelshop_city); ?><br>
			<?php endif; ?>

			<?php if ($parcelshop_country_name) : ?>
				<strong><?php echo __('Parcelshop country', 'wc-dpd'); ?></strong>: <?php echo esc_html($parcelshop_country_name); ?>
			<?php elseif ($parcelshop_country_code) : ?>
				<strong><?php echo __('Parcelshop country code', 'wc-dpd'); ?></strong>: <?php echo esc_html($parcelshop_country_code); ?>
			<?php endif; ?>
		<?php else : ?>
			<?php if ($parcelshop_name) : ?>
				<strong><?php echo esc_html($parcelshop_name); ?></strong><br>
			<?php endif; ?>

			<?php if ($parcelshop_street) : ?>
				<?php echo esc_html($parcelshop_street); ?><br>
			<?php endif; ?>

			<?php if ($parcelshop_zip || $parcelshop_city) : ?>
				<?php if ($parcelshop_zip) : ?>
					<?php echo esc_html($parcelshop_zip); ?>
				<?php endif; ?>

				<?php if ($parcelshop_city) : ?>
					<?php echo esc_html($parcelshop_city); ?>
				<?php endif; ?><br>
			<?php endif; ?>

			<?php if ($parcelshop_country_name) : ?>
				<?php echo esc_html($parcelshop_country_name); ?>
			<?php elseif ($parcelshop_country_code) : ?>
				<?php echo esc_html($parcelshop_country_code); ?>
			<?php endif; ?>
		<?php endif; ?>
	</p>
</div>
