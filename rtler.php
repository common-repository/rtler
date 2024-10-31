<?php
/*
Plugin Name: RTLer
Description: This plugin generates the RTL stylesheet for you from your theme's 'style.css' file.
Author: Louy Alakkad
Version: 2.0
Author URI: http://louyblog.wordpress.com/
Plugin URL: http://l0uy.wordpress.com/tag/rtler/
Text Domain: rtler
Domain Path: /languages
*/

// Change this to true to disable the save functionality.
// default: false
define('RTLER_SAVE_DISABLE', false);

/**
 * init RTLer by adding our page to the 'Tools' menu.
 */
function rtler_init() {
	add_submenu_page( 'tools.php', 'RTLer', 'RTLer', 'edit_themes', 'rtler', 'rtler_page' );
}
add_action( 'admin_menu', 'rtler_init' );

// Load translations
load_plugin_textdomain( 'rtler', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

/**
 * display the RTLer tool page.
 */
function rtler_page() {
	
	// theme, file and save fields values
	$theme = '';
	$file = 'style.css';
	$save = '';
	
	// get themes list
	$themes = array_merge(array(''=>array('data'=>'')),get_allowed_themes());
	
	// textareas values
	$rtled = '';
	$tortl = '';
	
	if( !RTLER_SAVE_DISABLE ) {
		
		// check for submitted filename
		if( isset( $_POST['file'] ) && $_POST['file'] ) {
			$file = $_POST['file'];
			$file = str_replace('\\','/',$file); // sanitize for Win32 installs
			$file = preg_replace('|\.\./|','/', $file); // remove any dir up string
			$file = preg_replace('|/+|','/', $file); // remove any duplicate slash
		}
		
		// check save option
		if( isset( $_POST['save'] ) && $_POST['save'] == 'true' ) {
			$save = true;
		}
		
		// check for submitted theme
		if( isset( $_POST['theme'] ) && !empty($_POST['theme']) ) {
			
			$theme = trim($_POST['theme']);
			
			// if we don't have a file name, use style.css
			if( empty( $file ) ) {
				$file = 'style.css';
			}
			
			// theme directory
			$dir = WP_CONTENT_DIR . '/themes/' . $theme . '/';
			
			// file path
			$path = dirname($dir.$file).'/'.basename($file);
			
			$path = str_replace('\\','/',$path); // sanitize for Win32 installs
			$path = preg_replace('|/+|','/', $path); // remove any duplicate slash
			
			// check if it's a css file
			if ( '.css' == substr( $file, strrpos( $file, '.' ) ) ) {
				
				if( is_file( $path ) ) { // check for file existance
					
					// read the file
					$f = fopen($path, 'r');
					$c = fread($f, filesize($path));
					fclose($f);
					
					$tortl = $c;
					
					// create RTL object
					$RTLer = new RTLer;
					
					// do our job! LOL
					$rtled = $RTLer->parse_css($c);
					
					if( $rtled ) {
						
						// now, save.
						if( $save ) {
							
							$error = false;
							
							$_file = preg_match( '/^(.*\\/)?style\.css$/', $path ) ? substr($path, 0, -9) . 'rtl.css' : substr($path, 0, -4) . '-rtl.css';
							
							if( is_file( $_file ) ) {
								
								// file exists so rename it.
								$__file = substr( $_file, 0, -4 ) . '.bak.css';
								$__file_b = substr( $_file, 0, -4 ) . '.bak-%%.css';
								
								$n = 0;
								while( is_file( $__file ) ) {
									$__file = str_replace( '%%', $n, $__file_b );
									$n ++;
								}
								
								unset( $n );
								
								rename($_file, $__file) or $error = true;
								
								if( $error )
									echo '<div id="message" class="error fade"><p><strong>'.sprintf(__('Error renaming <code>%s</code>, please edit manually.', 'rtler'),esc_html($_file)).'</strong></p></div>';
							}
							
							if( !$error ) {
								
								$fp = fopen($_file, 'w');
								
								if( $fp ) {
									
									// write new file
									fwrite( $fp, $rtled, strlen( $rtled ) );
									fclose( $fp );
									
									echo '<div id="message" class="updated fade"><p><strong>'.sprintf(__('File %s saved successfuly.', 'rtler'),esc_html($_file)).'</strong></p></div>';
									
								} else {
									$error = true;
									echo '<div id="message" class="error fade"><p><strong>'.sprintf(__('Error saving <code>%s</code>, file not writable.', 'rtler'),esc_html($_file)).'</strong></p></div>';
								}
							}
							
						}
						
					} else {
						// No need to rtl
						echo '<div id="message" class="updated fade"><p><strong>'.sprintf(__('File %s doesn&#38;t need any rtling.', 'rtler'),esc_html($_file)).'</strong></p></div>';
					}
						
				} else {
					echo '<div id="message" class="error fade"><p><strong>'.sprintf(__('the file <code>%s</code> was not found.', 'rtler'),esc_html($path)).'</strong></p></div>';
				}
			} else { // not a CSS file
				echo '<div id="message" class="error fade"><p><strong>'.__('The selected file is not a CSS file.', 'rtler').'</strong></p></div>';
			}
			
		}
	}
	
	if( isset( $_POST['tortl'] ) && !empty( $_POST['tortl'] ) ) { // we have file content submitted
		
		// get the submitted data
		$tortl = $_POST['tortl'];
		
		// create the RTL object
		$RTLer = new RTLer;
		
		// RTL!
		$rtled = $RTLer->parse_css($tortl);
	}
	
?>
<div class="wrap">

	<h2><?php _e('RTLer', 'rtler'); ?></h2>

	<div style="float:<?php echo is_rtl() ? 'left' : 'right'; ?>; margin: 5px;"><?php printf( __('Version %s by', 'rtler'), '2.0' ); ?> <a href="<?php _e('http://louyblog.wordpress.com/','rtler'	); ?>"><?php _e('Louy Alakkad','rtler'); ?></a></div>
	
<?php if( !RTLER_SAVE_DISABLE ) : ?>

	<p><?php _e('Select the CSS file you want to RTL.', 'rtler'); ?></p>

<form method="post" action="tools.php?page=rtler">
	
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="theme"><?php _e('Theme', 'rtler'); ?></label></th>
			<td>
				<select name="theme" id="theme">
					<?php
					foreach( $themes as $name => $data ) {
						?><option value="<?php echo esc_attr($data['Template']); ?>"<?php if( $data['Template'] == $theme ) { echo ' selected="selected"'; } ?>><?php echo $name; ?></option>
					<?php
					}
					?>

				</select>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="file"><?php _e('File path', 'rtler'); ?></label></th>
			<td>
				<input name="file" type="text" value="<?php echo esc_attr($file); ?>" id="file" class="regular-text" /> <small><?php _e('must be inside the theme directory, leave blank to use <code>style.css</code>. No <code>../</code> allowed!', 'rtler'); ?></small>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="save"><?php _e('Save file', 'rtler'); ?></label></th>
			<td>
				<input name="save" type="checkbox" value="true" <?php echo $save ? 'checked="checked" ' : ''; ?>id="save" /> <small><?php _e('check this to automatically save the rtled file.', 'rtler'); ?></small>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="rtled"><?php _e('RTLed file', 'rtler'); ?></label></th>
			<td>
				<textarea rows="10" cols="50" class="large-text code" readonly="readonly" id="rtled"><?php echo esc_html($rtled); ?></textarea>
			</td>
		</tr>
	</table>
		
	<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Generate rtl.css', 'rtler'); ?>" />
	</p>
</form>

<p><?php _e('Or, just enter the file contents here.', 'rtler'); ?></p>

<?php else: ?>

<p><?php _e('Enter the CSS file contents here.', 'rtler'); ?></p>

<?php endif; ?>

<form method="post" action="tools.php?page=rtler">
	
	<input type="hidden" name="page" value="rtler" />
	
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="tortl"><?php _e('CSS to RTL', 'rtler'); ?></label></th>
			<td>
				<textarea rows="10" cols="50" class="large-text code" id="tortl" name="tortl"><?php echo esc_html($tortl); ?></textarea>
				<small style="display:block;"><?php _e('I won&#39;t validate the CSS, I don&#39;t have time to!', 'rtler'); ?></small>
			</td>
		</tr>
	</table>
		
	<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('RTL!', 'rtler'); ?>" />
	</p>
</form>

</div><?php
}

/**
 * Load the RTLer class
 */
require_once dirname(__FILE__).'/class.php';