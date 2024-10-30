<style>
#imagecare-bar { position: relative; height:25px; margin: 12px 0px; border: 1px solid #0073AA; }
#imagecare-bar-progress { position: absolute; top: 0%; left: 0%; width: 0%; height: 100%; background: lightgray; }
#imagecare-bar-percent { position: absolute; top: 50%; left: 50%; width: 300px; height: 25px; margin-top: -9px; margin-left: -150px; font-weight: bold; text-align: center; }
</style>
<?php
	$letsgo = !empty($_POST['letsgo']) ? (bool) $_POST['letsgo'] : false;
	if(!$letsgo) {
?>
<form method="post" action="">
	<?php wp_nonce_field('imagecare', 'regensizes') ?>
	<p>If you have added a new size or changed the resolution of an existing size, you need to regenerate the files but you don´t need to regenerate them for all the configured sizes.</p>
	<p>I offer you a way to regenerate only some sizes.</p>
	<?php $sizes = $this->get_image_sizes(); ?>
	<table class="form-table">
	<tbody>
		<tr>
			<th><label>Defined sizes</label></th>
			<td>
				<input type="checkbox" name="dtop" value="x" checked /> All <br/>
				<div style="column-count: 3"><?php foreach($sizes as $name=>$size) { ?>
				<input type="checkbox" name="dtop[]" value="<?php echo $name ?>" /> <?php echo $name.' ('.$size['width'].'x'.$size['height'].')' ?><br/>
				<?php } ?></div>
			</td>
		</tr>
	</tbody>
	</table>
	<p><input type="submit" class="button hide-if-no-js" name="letsgo" value="<?php _e( 'Regenerate', 'regensizes' ) ?>" /></p>
	<noscript><p><em><?php _e( 'You must enable Javascript in order to proceed!', 'imagecare' ) ?></em></p></noscript>
</form>
<?php
	} else {
		if($this->check_before_do('regensizes')) {
			$dtop   = isset($_POST['dtop']) ? $_POST['dtop'] : 'x';
			$hr_ids = $this->getImageIDs();
			$count  = count($hr_ids);
			$text_failures   = sprintf( __( 'All done! %1$s image(s) were successfully resized but there were %2$s failure(s).', 'imagecare' ), "'+rt_successes+'", "'+rt_errors+'" );
			$text_nofailures = sprintf( __( 'All done! %1$s image(s) were successfully resized and there were 0 failures.', 'imagecare' ), "'+rt_successes+'" );
?>
	<div id="imagecare-bar">
		<div id="imagecare-bar-progress"></div>
		<div id="imagecare-bar-percent">Starting...</div>
	</div>

	<p><input type="button" class="button hide-if-no-js" name="imagecare-stop" id="imagecare-stop" value="<?php _e('Abort process', 'imagecare') ?>" /></p>

	<h3 class="title"><?php _e('Process information', 'imagecare') ?></h3>
	<p>
		<?php printf( __( 'Total images: %s',    'imagecare' ), $count ); ?><br />
		<?php printf( __( 'Images resized: %s',  'imagecare' ), '<span id="imagecare-debug-successcount">0</span>' ); ?><br />
		<?php printf( __( 'Resize failures: %s', 'imagecare' ), '<span id="imagecare-debug-failurecount">0</span>' ); ?>
	</p>

	<ol id="imagecare-debuglist"></ol>

	<script type="text/javascript">
		jQuery(document).ready(function($){
			var i;
			var rt_images     = [<?php echo implode(chr(44), $hr_ids) ?>];
			var rt_total      = rt_images.length;
			var rt_current    = 1;
			var rt_percent    = 0;
			var rt_successes  = 0;
			var rt_errors     = 0;
			var rt_failedlist = '';
			var rt_resulttext = '';
			var rt_timestart  = new Date().getTime();
			var rt_timeend    = 0;
			var rt_totaltime  = 0;
			var rt_prev_size  = 0;
			var rt_curr_size  = 0;
			var rt_saved      = 0;
			var rt_alive      = true;

			var jMessage     = $('#message');
			var jBarProgress = $('#imagecare-bar-progress');
			var jBarPercent  = $('#imagecare-bar-percent').html('0%');
			var jBtnStop     = $('#imagecare-stop');
			var jDebugCountS = $('#imagecare-debug-successcount');
			var jDebugCountF = $('#imagecare-debug-failurecount');
			var jDebugPrevS  = $('#imagecare-debug-previoussize');
			var jDebugCurrS  = $('#imagecare-debug-currentsize');
			var jDebugSaved  = $('#imagecare-debug-saved');
			var jDebugList   = $('#imagecare-debuglist').empty();

			jBtnStop.click(function() {
				rt_alive = false;
				jBtnStop.val('<?php _e('Stopping...', 'imagecare') ?>');
			});

			function imagecareUpdateStatus( id, success, response ) {
				var sPercent = Math.round( (rt_current/rt_total)*1000 ) / 10 + '%';
				jBarProgress.width( sPercent ); // ((rt_current/rt_total)*100)+'%'
				jBarPercent.html( sPercent );
				rt_current++;
				if(success) {
					rt_successes++;
					jDebugCountS.html(rt_successes);
					jDebugPrevS.html(parseInt(rt_prev_size += response.size_b/1024)+' Mb');
					jDebugCurrS.html(parseInt(rt_curr_size += response.size_a/1024)+' Mb');
					jDebugSaved.html(parseInt(rt_saved     += response.saved/1024 )+' Mb');
					jDebugList.append('<li>' + response.success + '</li>');
				} else {
					rt_errors++;
					rt_failedlist += ',' + id;
					jDebugCountF.html(rt_errors);
					jDebugList.append('<li>' + response.error + '</li>');
				}
			}

			function imagecareFinishUp() {
				rt_timeend = new Date().getTime();
				rt_totaltime = Math.round( ( rt_timeend - rt_timestart ) / 1000 );

				jBtnStop.hide();

				if(rt_errors > 0) {
					rt_resulttext = '<?php echo $text_failures; ?>';
				} else {
					rt_resulttext = '<?php echo $text_nofailures; ?>';
				}
				jMessage.html('<p><strong>' + rt_resulttext + '</strong></p>');
				jMessage.show();
			}

			function do_it(id) {
				jQuery.ajax({
					type: 'POST',
					url: ajaxurl,
					data: { 'action': 'imagecare_regensizes', 'id': id, 'sizes': '<?php echo implode(chr(44), $dtop) ?>' },
					success: function(response) {
						if(response!==Object(response) || (typeof response.success==='undefined' && typeof response.error==='undefined')) {
							response = new Object;
							response.success = false;
							response.error = '<?php printf( esc_js( __( 'The resize request was abnormally terminated (ID %s). This is likely due to the image exceeding available memory or some other type of fatal error.', 'imagecare' ) ), '" + id + "' ); ?>';
						}
						if(response.success) {
							imagecareUpdateStatus(id, true, response);
						} else {
							imagecareUpdateStatus(id, false, response);
						}
						if(rt_images.length && rt_alive) {
							do_it(rt_images.shift());
						} else {
							imagecareFinishUp();
						}
					},
					error: function(response) {
						imagecareUpdateStatus(id, false, response);
						if(rt_images.length && rt_alive) {
							do_it(rt_images.shift());
						} else {
							imagecareFinishUp();
						}
					}
				});
			}

			do_it(rt_images.shift());
		});
	</script><?php
		}
	}
?>