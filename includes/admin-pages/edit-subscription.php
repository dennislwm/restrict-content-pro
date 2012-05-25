<?php
$level = rcp_get_subscription_details(urldecode($_GET['edit_subscription']));
?>
<h2>
	<?php _e('Edit Subscription Level:', 'rcp'); echo ' ' . utf8_decode($level->name); ?> - 
	<a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=rcp-member-levels'; ?>" class="button-secondary">
		<?php _e('Cancel', 'rcp'); ?>
	</a>
</h2>
<form id="rcp-edit-subscription" action="" method="post">
	<table class="form-table">
		<tbody>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-name"><?php _e('Name', 'rcp'); ?></label>
				</th>
				<td>
					<input name="name" id="rcp-name" type="text" value="<?php echo stripslashes(utf8_decode($level->name)); ?>"/>
					<p class="description"><?php _e('The name of this subscription. This is shown on the registration page.', 'rcp'); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-description"><?php _e('Description', 'rcp'); ?></label>
				</th>
				<td>
					<textarea name="description" id="rcp-description"><?php echo stripslashes(utf8_decode($level->description)); ?></textarea>
					<p class="description"><?php _e('The description of this subscription. This is shown on the registration page.', 'rcp'); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-level"><?php _e('Access Level', 'rcp'); ?></label>
				</th>
				<td>
					<select id="rcp-level" name="level">
						<?php
						for($i = 0; $i <= 10; $i++) {
							echo '<option value="' . $i . '" ' . selected($i, $level->level, false) . '">' . $i . '</option>';
						}	
						?>
					</select>
					<p class="description"><?php _e('Level of access this subscription gives.', 'rcp'); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-duration"><?php _e('Duration', 'rcp'); ?></label>
				</th>
				<td>
					<input type="text" id="rcp-duration" style="width: 40px;" name="duration" value="<?php echo $level->duration; ?>"/>
					<select name="duration-unit" id="rcp-duration-unit">
						<option value="day" <?php selected($level->duration_unit, 'day'); ?>><?php _e('Days(s)', 'rcp'); ?></option>
						<option value="month" <?php selected($level->duration_unit, 'month'); ?>><?php _e('Month(s)', 'rcp'); ?></option>
						<option value="year" <?php selected($level->duration_unit, 'year'); ?>><?php _e('Years(s)', 'rcp'); ?></option>
					</select>
					<p class="description"><?php _e('Length of time for this membership level. Enter 0 for unlimited.', 'rcp'); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-price"><?php _e('Price', 'rcp'); ?></label>
				</th>
				<td>
					<input type="text" id="rcp-price" name="price" value="<?php echo $level->price; ?>" style="width: 40px;"/>
					<p class="description"><?php _e('The price of this membership level. Enter 0 for free.', 'rcp'); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-status"><?php _e('Status', 'rcp'); ?></label>
				</th>
				<td>
					<select name="status" id="rcp-status">
						<option value="active" <?php selected($level->status, 'active'); ?>><?php _e('Active', 'rcp'); ?></option>
						<option value="inactive" <?php selected($level->status, 'inactive'); ?>><?php _e('Inactive', 'rcp'); ?></option>
					</select>
					<p class="description"><?php _e('Members may only sign up for active subscription levels.', 'rcp'); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<p class="submit">
		<input type="hidden" name="rcp-action" value="edit-subscription"/>
		<input type="hidden" name="subscription_id" value="<?php echo urldecode($_GET['edit_subscription']); ?>"/>
		<input type="submit" value="<?php _e('Update Subscription', 'rcp'); ?>" class="button-primary"/>
	</p>
</form>