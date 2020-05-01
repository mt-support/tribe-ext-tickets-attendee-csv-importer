<?php
/**
 * @var string   $help
 * @var int      $selected_event_id
 * @var string[] $events
 * @var string   $provider
 */
?>

<tr class="tribe-dependent" data-depends="#tribe-ea-field-csv_content_type" data-condition="<?php echo esc_attr( $provider ); ?>">
	<th scope="row">
		<label for="tribe-ea-field-csv_<?php echo esc_attr( $provider ); ?>_event">
			<?php esc_html_e( 'Event', 'tribe-ext-tickets-attendee-csv-importer' ); ?>
		</label>
	</th>
	<td>
		<select
			name="aggregator[csv][<?php echo esc_attr( $provider ); ?>_event]"
			id="tribe-ea-field-csv_<?php echo esc_attr( $provider ); ?>_event"
			class="tribe-ea-field tribe-ea-dropdown tribe-ea-size-large tribe-ea-field-csv_<?php echo esc_attr( $provider ); ?>_event"
		>
			<option value=""<?php selected( '', $selected_event_id ); ?>>
				<?php esc_html_e( 'Detect from Import', 'tribe-ext-tickets-attendee-csv-importer' ); ?>
			</option>
			<?php foreach ( $events as $event_id => $event_title ) : ?>
				<option value="<?php echo esc_attr( $event_id ); ?>"<?php selected( $event_id, $selected_event_id ); ?>>
					<?php echo esc_html( $event_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<span class="tribe-bumpdown-trigger tribe-bumpdown-permanent tribe-bumpdown-nohover tribe-ea-help dashicons dashicons-editor-help"
			data-bumpdown="<?php echo esc_attr( $help ); ?>"
			data-width-rule="all-triggers"></span>
	</td>
</tr>

<tr class="tribe-dependent" data-depends="#tribe-ea-field-csv_content_type" data-condition="<?php echo esc_attr( $provider ); ?>">
	<th scope="row">
		<label for="tribe-ea-field-csv_<?php echo esc_attr( $provider ); ?>_send_email">
			<?php esc_html_e( 'Emails', 'tribe-ext-tickets-attendee-csv-importer' ); ?>
		</label>
	</th>
	<td>
		<label for="tribe-ea-field-csv_<?php echo esc_attr( $provider ); ?>_send_email">
			<input
				name="aggregator[csv][<?php echo esc_attr( $provider ); ?>_send_email]"
				id="tribe-ea-field-csv_<?php echo esc_attr( $provider ); ?>_send_email"
				class="tribe-ea-field tribe-ea-field-csv_<?php echo esc_attr( $provider ); ?>_send_email"
				value="1"
				type="checkbox"
				checked
			 />
			<?php esc_html_e( 'Send attendee emails', 'tribe-ext-tickets-attendee-csv-importer' ); ?>
		</label>
	</td>
</tr>
