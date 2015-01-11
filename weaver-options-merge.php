<?php
/*
Plugin Name: Weaver Options Merge
Plugin URI: http://joyreynolds.com/downloads/category/plugin/
Description: Merge two settings files from Weaver II, Aspen, Weaver Xtreme, or Weaver
Author: Joy Reynolds
Author URI: http://joyreynolds.com
Text Domain: womjoy
Domain Path: /lang
Version: 0.2
License: GPL

Weaver Options Merge
Copyright (C) 2014, Joy Reynolds

GPL License: http://www.opensource.org/licenses/gpl-license.php

This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

if ( ! function_exists( 'add_filter' ) ) {	// must run under WordPress
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

define ( 'WOM_MERGE_VERSION', '0.2' );
define ( 'WOM_MERGE_SESSION', 'WOMerge' );
define ( 'WOM_MERGE_FILES', 'wommerge_file_list' );

if(isset($_POST['wompage']) && $_POST['wompage'] == 'wom_merge_export' && wom_merge_valid_theme( $_POST['womtheme'] ) ) {
	add_action('init', 'wom_merge_admin_process');	 // called direct for file output
}

load_plugin_textdomain('womjoy', FALSE, dirname(plugin_basename(__FILE__)) . '/lang');
add_action( 'admin_menu', 'wom_merge_add_admin_menu' );
add_action( 'admin_init', 'wom_merge_admin_init' );
register_deactivation_hook( __FILE__, 'wom_merge_deactivate' );

function wom_merge_add_admin_menu() {
	// 									 $page_title, $menu_title, $capability, $menu_slug, $function );
	$my_page = add_management_page(__( 'Weaver Options Merge', 'womjoy' ), __( 'Weaver Options Merge', 'womjoy' ),
		'manage_options', 'wom-merge', 'wom_merge_admin_page');
	if ( $my_page )	{			 // load only on my plugin page
		add_action('admin_print_scripts-'.$my_page, 'wom_merge_load_scripts');
		add_action('admin_print_styles-'.$my_page, 'wom_merge_load_styles');
	}
} //wom_merge_add_admin_menu

function wom_merge_admin_init() {
	wom_merge_show_input_validate();	// session must be started before output starts
	wp_register_script( 'wom-merge', plugins_url( '/wom-merge.js', __FILE__ ), array( 'jquery', 'jquery-ui-accordion' ) );
} //wom_merge_admin_init

function wom_merge_load_styles() {
	wp_enqueue_style( 'wom-merge', plugins_url( '/wom-merge.css', __FILE__ ) );
}

// set the variables the javascript needs
function wom_merge_load_scripts() {
	wp_enqueue_script( 'wom-merge' );
	$order = array( 'weaver-ii', 'aspen', 'weaver-xtreme', 'weaver' ); // needs to match sections in wom_merge_admin_page
	$scriptvars = array(
		'mainAccordion' => 'womaccordion',
		'activeSection' => isset( $_POST['womtheme'] ) ? array_search( $_POST['womtheme'], $order ) : false,
		'accnames' => array('#wom_weaveriiaccordion0', '#wom_aspenaccordion0', '#wom_weaverxaccordion0', '#wom_weaveraccordion0',
		'#wom_weaveriiaccordion1', '#wom_aspenaccordion1', '#wom_weaverxaccordion1', '#wom_weaveraccordion1'),
		'tableName' => 'womlist',
		'choiceClass' => 'womselected',
		'mergeForm' => 'wommergeform',
		'mergeButton' => 'wom_merge_files',
		'showButtons' => 'womshowmerge',
		'showRadio' => 'womradio',
		'mergeChoice' => 'womchoice',
		'siteOption' => 'wombackup',
		);
	wp_localize_script( 'wom-merge', 'womMergeVars', apply_filters( 'wom_script_vars', $scriptvars ) );
	$scriptmsgs = array(
		'nothing' => __( 'Nothing to merge', 'womjoy' ),
		'needtwo' => __('Need two option sets to merge', 'womjoy' ),
		'allselbutton' => __('All', 'womjoy' ),
		'colorselbutton' => __('Colors', 'womjoy' ),
		'clrcssselbutton' => 'CSS+',
		'insselbutton' => __('insert', 'womjoy' ),
		'fontselbutton' => __('font', 'womjoy' ),
		'siteselbutton' => __('Site', 'womjoy' ),
		);
	wp_localize_script( 'wom-merge', 'womMergeMsgs', apply_filters( 'wom_script_msgs', $scriptmsgs ) );
} //wom_merge_load_scripts

//---------------------------------------------------------------------------------
// theme name needed for finding built-in option sets
function wom_merge_valid_theme( $theme_name ) {
	$good = array( 'aspen', 'weaver-ii', 'weaver-xtreme', 'weaver' );
	if ( in_array( strtolower( $theme_name ), $good ) )
		return strtolower( $theme_name );
	else return false;
} //wom_merge_valid_theme

// theme abbreviation needed for option names
function wom_merge_abbrev( $theme_name ) {
	switch ( strtolower( $theme_name ) ) {
		case 'aspen': $out = 'aspen'; break;
		case 'weaver': $out = 'weaver'; break;
		case 'weaver-xtreme': $out = 'weaverx'; break;
		case 'weaver-ii':
		default:
			$out = 'weaverii';
	}
	return $out;
} //wom_merge_abbrev

// get a list of option names matching a theme
function wom_merge_get_options_from_db( $match ) {
	global $wpdb;
	$like = '%'. addcslashes( $match, '_%\\' ) .'%';
	$query = $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", $like );
	$results = apply_filters( 'wom_merge_options_query', $wpdb->get_results( $query ), $match );
	return $results;  // array of objects
} //wom_merge_get_options_from_db

// create HTML to list the option sets in the database
function wom_merge_create_option_list( $theme_abbrev, $radio ) {
	$content = array();
	$content[0] = $content[1] = '';
	if ( current_user_can( 'manage_options' ) ) {
		$theme_settings_name = $theme_abbrev.( $theme_abbrev=='weaver' ? '_main' : '' ).'_settings';
		$switcher = $theme_abbrev.'_switch_themes';
		$theme_options = wom_merge_get_options_from_db( $theme_settings_name );
		if ( $theme_options ) {
			$content[0] .= '<h4>'.__( 'Database option sets:', 'womjoy' ) .'</h4><div>';
			$content[1] .= '<h4>'.__( 'Database option sets:', 'womjoy' ) .'</h4><div>';
			foreach ( $theme_options as $row ) {
				$name = esc_html( ucwords( str_replace( '_', ' ', $row->option_name ) ) );
				if ( $theme_settings_name == $row->option_name )
					$name = __( 'Current', 'womjoy' ).' '.$name;
				$content[0] .= '<p><label><input type="radio" name="' . $radio . '_0" value="' . esc_attr( $row->option_name ) . '" ';
				$content[0] .= (isset( $_POST[$radio.'_0'] ) ? checked( $row->option_name, $_POST[$radio.'_0'], false) : '' ) . ' /> ' . $name . '</label></p>';
				$content[1] .= '<p><label><input type="radio" name="' . $radio . '_1" value="' . esc_attr( $row->option_name ) . '" ';
				$content[1] .= (isset( $_POST[$radio.'_1'] ) ? checked( $row->option_name, $_POST[$radio.'_1'], false) : '' ) . ' /> ' . $name . '</label></p>';
			}
			$content[0] .= '</div>';
			$content[1] .= '</div>';
		}
		$theme_options = apply_filters( 'wom_merge_get_switch', get_option( $switcher, array() ), $switcher );
		if ( $theme_options ) {
			$content[0] .= '<h4>'.__( 'Theme Switcher option sets:', 'womjoy' ) .'</h4><div>';
			$content[1] .= '<h4>'.__( 'Theme Switcher option sets:', 'womjoy' ) .'</h4><div>';
			foreach ( array_keys( $theme_options ) as $row ) {
				$name = esc_html( ucwords( str_replace( '_', ' ', $row ) ) );
				$content[0] .= '<p><label><input type="radio" name="' . $radio . '_0" value="' . esc_attr( $switcher.'+wom+'.$row ) . '" ';
				$content[0] .= (isset( $_POST[$radio.'_0'] ) ? checked( $switcher.'+wom+'.$row, $_POST[$radio.'_0'], false ) : '') . ' /> ' . $name . '</label></p>';
				$content[1] .= '<p><label><input type="radio" name="' . $radio . '_1" value="' . esc_attr( $switcher.'+wom+'.$row ) . '" ';
				$content[1] .= (isset( $_POST[$radio.'_1'] ) ? checked( $switcher.'+wom+'.$row, $_POST[$radio.'_1'], false ) : '') . ' /> ' . $name . '</label></p>';
			}
			$content[0] .= '</div>';
			$content[1] .= '</div>';
		}
	}
	return $content;
} //wom_merge_create_option_list

//---------------------------------------------------------------------------------
// functions used for filter 'wom_merge_file_ext'
function wom_merge_weaverii_file_ext( $ext_array ) { return array( 'W2T', 'W2B' ); }
function wom_merge_aspen_file_ext( $ext_array ) { return array( 'ATH', 'ABU' ); }
function wom_merge_weaverx_file_ext( $ext_array ) { return array( 'WXT', 'WXB' ); }
function wom_merge_weaver_file_ext( $ext_array ) { return array( 'WVR', 'WVB' ); }

// utility for wom_merge_get_files_from_dir
function wom_merge_match_extension( $var ) {
	return in_array( strtoupper( substr( $var, -3 ) ), apply_filters( 'wom_merge_file_ext', array( ) ) );
}

// get a list of files matching an extension, save in a transient
function wom_merge_get_files_from_dir( $dir ) {
	$list = get_transient( WOM_MERGE_FILES );
	if ( false === $list || !array_key_exists( $dir, $list ) ) {
		$files = list_files( $dir );
		$list[$dir] = $files;
		set_transient( WOM_MERGE_FILES, $list, 60*60*12 );
	}
	else $files = $list[$dir];
	$files = array_filter( $files, 'wom_merge_match_extension' );
	$files = apply_filters( 'wom_merge_files_query', $files, $dir );
	return $files;  // array of strings
} //wom_merge_get_files_from_dir

// each file list has a title and a set of radio inputs
function wom_merge_list_file_inputs( $dir, $title, $radio, &$content ) {
	$the_files = wom_merge_get_files_from_dir( $dir );
	if ( $the_files ) {
		$content[0] .= '<h4>'.$title .'</h4><div>';
		$content[1] .= '<h4>'.$title .'</h4><div>';
		foreach ( $the_files as $subtheme ) {
			$name = esc_html( ucwords( str_replace( '-', ' ', basename( $subtheme ) ) ) );
			$content[0] .= '<p><label><input type="radio" name="' . $radio . '_0" value="' . esc_attr( $subtheme ) . '" ';
			$content[0] .= (isset( $_POST[$radio.'_0'] ) ? checked( $subtheme, $_POST[$radio.'_0'], false) : '' ) . ' /> ' . $name . '</label></p>';
			$content[1] .= '<p><label><input type="radio" name="' . $radio . '_1" value="' . esc_attr( $subtheme ) . '" ';
			$content[1] .= (isset( $_POST[$radio.'_1'] ) ? checked( $subtheme, $_POST[$radio.'_1'], false) : '' ) . ' /> ' . $name . '</label></p>';
		}
		$content[0] .= '</div>';
		$content[1] .= '</div>';
	}
} //wom_merge_list_file_inputs

// create HTML to list the option set files
function wom_merge_create_file_list( $theme_name, $radio ) {
	$theme_abbrev = wom_merge_abbrev( $theme_name );
	$prefix = 'wom_'.$theme_abbrev;
	$content = array();
	$content[0] = $content[1] = '';
	$theme_info = wp_get_theme( $theme_name );
	$upload_dir = wp_upload_dir();
	add_filter( 'wom_merge_file_ext', 'wom_merge_'.$theme_abbrev.'_file_ext');
	if ( current_user_can( 'manage_options' ) ) {
		wom_merge_list_file_inputs( wp_normalize_path( $upload_dir['basedir'] ).'/'.$theme_abbrev.'-subthemes',
				__( 'Saved option sets:', 'womjoy' ), $radio,	$content );
	}
	wom_merge_list_file_inputs( wp_normalize_path( $theme_info->get_stylesheet_directory() ),
			__( 'Theme built-in option sets:', 'womjoy' ), $radio, $content );
	$content[0] .= '<h4>'.__( 'Upload:', 'womjoy' ) .'</h4><div>';
	$content[1] .= '<h4>'.__( 'Upload:', 'womjoy' ) .'</h4><div>';
	$content[0] .= '<p><input type="radio" name="' . $radio . '_0" value="+womfile+" id="'.$prefix.'_womfile0" ';
	$content[0] .= (isset( $_POST[$radio.'_0'] ) ? checked( '+womfile+', $_POST[$radio.'_0'], false) : '' ) . ' /> ';
	$content[0] .= '<label for="'.$prefix.'_womfile0">'.__( 'Upload a settings file', 'womjoy' ).' <input type="file" name="'.$prefix.'_settings_file0" value="" /></label></p>';
	$content[0] .= '</div>';
	$content[1] .= '<p><input type="radio" name="' . $radio . '_1" value="+womfile+" id="'.$prefix.'_womfile1" ';
	$content[1] .= (isset( $_POST[$radio.'_1'] ) ? checked( '+womfile+', $_POST[$radio.'_1'], false) : '' ) . ' /> ';
	$content[1] .= '<label for="'.$prefix.'_womfile1">'.__( 'Upload a settings file', 'womjoy' ).' <input type="file" name="'.$prefix.'_settings_file1" value="" /></label></p>';
	$content[1] .= '</div>';
	$temp = array();	$temp[0] = $temp[1] = '';
	wom_merge_list_file_inputs( wp_normalize_path( plugin_dir_path( __FILE__ ) ).'optionsets',
			__( 'Plugin provided option sets:', 'womjoy' ), $radio,	$temp );
	$content[1] .= $temp[1];
	remove_filter( 'wom_merge_file_ext', 'wom_merge_'.$theme_abbrev.'_file_ext');
	return $content;
} //wom_merge_create_file_list

//---------------------------------------------------------------------------------
// create the HTML form for listing option sets
function wom_merge_create_list_form( $theme_name ) {
	$theme_abbrev = wom_merge_abbrev( $theme_name );
	$prefix = 'wom_'.$theme_abbrev;
	$content = '<p>'. __( 'Choose two option sets to show. The left set will show as selected by default, for the merge.', 'womjoy' ) .'</p>';
	$content .= '<form method="post" enctype="multipart/form-data">';
	$content .= '	<input type="hidden" name="wompage" value="wom_merge_show" />';
	$content .= '	<input type="hidden" name="womtheme" value="'.$theme_name.'" />';
	$content .= '<input type="hidden" name="MAX_FILE_SIZE" value="100000" />';
  $bothopts = wom_merge_create_option_list( $theme_abbrev, $prefix.'_optset' );
  $bothfiles = wom_merge_create_file_list( $theme_name, $prefix.'_optset' );
	$content .= '<div id="'.$prefix.'accordion0" class="womleft">';
	$content .= $bothopts[0];
	$content .= $bothfiles[0];
	$content .= '</div>';
	$content .= '<div  id="'.$prefix.'accordion1" class="womright">';
	$content .= $bothopts[1];
	$content .= $bothfiles[1];
	$content .= '</div><div style="clear:both"></div>';
	$content .= wp_nonce_field( $prefix.'showmerge', $prefix.'showmergeID', true, false );
	$content .= '<p><input type="submit" class="button-primary womshowmerge" name="'.$prefix.'_showmerge" value="'. __( 'Show side by side', 'womjoy' ).'" data-womradio="'.$prefix.'_optset" /></p>';
	$content .= '</form>';
	if ( isset( $_POST['wompage'] ) && $_POST['wompage'] == 'wom_merge_show' ) {
		if ( wom_merge_valid_theme( $_POST['womtheme'] ) && $_POST['womtheme'] == $theme_name ) {
			if ( $_SESSION['womshow_base']['msg'] || $_SESSION['womshow_overlay']['msg'] )
				$content .= '<p class="error">'.$_SESSION['womshow_base']['msg'].' '.$_SESSION['womshow_overlay']['msg'].'</p>';
			if ( isset( $_SESSION['womshow_base']['data'] ) ) {
				$content .= wom_merge_create_merge_form( $_POST['womtheme'], $_SESSION['womshow_base'], $_SESSION['womshow_overlay'] );
			}
		}
	}
	return $content;
} //wom_merge_create_list_form

//---------------------------------------------------------------------------------
// determine if an option is a CSS+ box
function wom_merge_is_css( $key, $value ) {
	return ( (substr( $key, -4 ) == '_css' || substr( $key, 0, 4 ) == 'css_') && is_string( $value ) );
}

// create HTML to show a sample of the color and CSS options
function wom_merge_show_sample( $key, $value ) {
	$out = esc_html( $value );
	if ( $out ) {
		if ( substr( $key, -5 ) == 'color' ) {
			$out .= ' <div class="womcolor" style="background-color:'. esc_attr( $value ).'"></div>';
			if ( substr( $key, -6 ) == '_color' )
				$out .= ' <span style="color:'. esc_attr( $value ).'">Sample</span>';
		}
		else if ( wom_merge_is_css( $key, $value ) ) {
			$temp = trim( wom_merge_extract_css( $value ) );
			$temp = substr( $temp, strpos( $temp, '{' ) );
			if ( trim($temp) != '{}' )
				$out = '<a class="wominfo" href="#">&#9432;<div class="womshowcss"><div class="womsamplecss" style="'. esc_attr( substr( $temp, strpos( $temp, '{' )+1, -1 ) ).'">Sample of '.esc_html( $temp ).'</div></div></a> '. $out;
		}
	}
	return $out;
} //wom_merge_show_sample

function wom_merge_radio( $key, $value ) {
	return '<input type="radio" name="womchoice['. esc_attr( $key ) .']" value="'. esc_attr( $value ) .'" '. checked( $value, 1, false ). ' />';
}

// create the table of options presented in the merge form
function wom_merge_create_merge_table( $basetomerge, $othertomerge, $headingrow, $backupkeys ) {
	$out = '<table id="womlist"><tbody>'.$headingrow;
	foreach( $basetomerge as $k=>$v ) {
		$cssclass = wom_merge_is_css( $k, $v ) ? 'class="womcss"' : '';
		$backupclass = ( $k[0] == '_' || in_array( $k, $backupkeys ) ) ? 'class="wombackup"' : '';
		$ochoice = array_key_exists( $k, $othertomerge ) ? 2 : 0;
		$v2 = isset( $othertomerge[$k] ) ? $othertomerge[$k] : '';
		if ( $v == $v2 ) {
			if ( !empty( $v ) )	{
				$out .= "<tr $backupclass>\n";
				$out .= "<td><div $cssclass>". wom_merge_show_sample( $k, $v )."</div></td>";
				$out .= '<td><div class="womkey">'. esc_html( $k ) .'</div></td>';
				$out .= "<td><div $cssclass>". wom_merge_show_sample( $k, $v2 )."</div></td></tr>\n";
			}
		continue;
		}
		$merge = $cssclass || ( substr( $k, -4 ) == 'html' ) || ( substr( $k, -6 ) == 'insert' ) || ( substr( $k, -5 ) == 'notes' ) || ( substr( $k, -5 ) == '_opts' && is_string( $v ) );
		if ( $merge )
			$merge = !empty( $v ) && !empty( $v2 );
		$out .= "<tr $backupclass>\n";
		$out .= "<td class=\"womselected\"><div $cssclass><label>".wom_merge_radio( $k, 1 ). wom_merge_show_sample( $k, $v )."</label></div></td>";
		if ( $merge )
			$out .= '<td><div class="womkey"><label>'. esc_html( $k ) .'<br /><span>'. __( 'Merge', 'womjoy' ).'</span>' . ($merge? wom_merge_radio( $k, 3 ) : '')."</label></div></td>";
		else
			$out .= '<td><div class="womkey">'. esc_html( $k ) .'</div></td>';
		$out .= "<td><div $cssclass><label>".wom_merge_radio( $k, $ochoice ). wom_merge_show_sample( $k, $v2 )."</label></div></td></tr>\n";
	}
	foreach( array_diff_key( $othertomerge, $basetomerge ) as $k=>$v ) {
		if ( !empty( $v ) ) {
			$cssclass = wom_merge_is_css( $k, $v ) ? 'class="womcss"' : '';
			$backupclass = ( $k[0] == '_' || in_array( $k, $backupkeys ) ) ? 'class="wombackup"' : '';
			$out .= "<tr $backupclass>\n";
			$out .= "<td class=\"womselected\"><div $cssclass><label>".wom_merge_radio( $k, 1 )." </label></div></td>";
			$out .= '<td><div class="womkey">'. esc_html( $k ) .'</div></td>';
			$out .= "<td><div $cssclass><label>".wom_merge_radio( $k, 2 ). wom_merge_show_sample( $k, $v )."</label></div></td></tr>\n";
		}
	}
	$out .= "</tbody></table>\n";
	return $out;
} //wom_merge_create_merge_table

// create the HTML form to handle the merge
function wom_merge_create_merge_form( $theme_name, $baseset, $otherset ) {
	$theme_abbrev = wom_merge_abbrev( $theme_name );
	$basetomerge = $baseset['data'];
	$othertomerge = array_key_exists( 'data', $otherset ) ? $otherset['data'] : array();
	$out = '<form id="wommergeform" method="post">';
	$out .= '	<input type="hidden" name="wompage" value="wom_merge_export" />';
	$out .= '	<input type="hidden" name="womtheme" value="'.$theme_name.'" />';
	$heading = ' <tr><th>'.$baseset['outname'].'</th><th></th><th>'.$otherset['outname']."</th></tr>\n";
	$out .= wom_merge_create_merge_table( $basetomerge, $othertomerge, $heading, array_keys( array_merge( $baseset['pro'], $otherset['pro'] ) ) );
	$out .= '<p class="wombackup">'.__( 'Site-specific options (&diams;) have a darker background color.', 'womjoy' ).'</p>';
	if ( count( $basetomerge ) > 0 && count( $othertomerge ) > 0) {
		$out .= wp_nonce_field( 'wommergefile', $theme_abbrev.'mergeIDfile', true, false );
		$out .= '<p><input type="submit" class="button-primary" id="wom_merge_files" name="wom_merge_files" value="'. __( 'Merge Files', 'womjoy' ).'"/></p>';
		$instruct = '<p>'.__( 'Choose the options to write to the merged file. The buttons below select subsets.', 'womjoy' )."</p>\n";
	}
	else $instruct = '';
	$out .= '</form>';
	return '<hr>'.$instruct.$out;
} //wom_merge_create_merge_form

//---------------------------------------------------------------------------------
// determine if a theme settings file matches the extension
function wom_merge_file_identify( $theme_abbrev, $identifier ) {
	$full = array( 'weaverii'=>'W2B-V01.00', 'aspen'=>'ABU-V01.00', 'weaverx'=>'WXB-V01.00', 'weaver'=>'WVB-V02.00' );
	$subset = array( 'weaverii'=>'W2T-V01.00', 'aspen'=>'ATH-V01.00', 'weaverx'=>'WXT-V01.00', 'weaver'=>'TTW-V01.10' );
	if ( $full[$theme_abbrev] == $identifier ) $output_type = 'backup';
	else if ( $subset[$theme_abbrev] == $identifier ) $output_type = 'theme';
	else $output_type = false;
	return $output_type;
} //wom_merge_file_identify

// load the options from the source chosen by user
function wom_merge_load_option_set( $theme_name, $postedname, $postedfile ) {
	$theme_abbrev = wom_merge_abbrev( $theme_name );
	$prefix = 'wom_'.$theme_abbrev;
	$msg = $outname = $type = '';
	$pro = array();
	if ( isset( $_POST[$postedname] ) ) {
		if ( strpos( $_POST[$postedname], '+womfile+' ) === 0 )	{
			if ( $_FILES[$postedfile]['size'] ) {
				$data = file_get_contents( $_FILES[$postedfile]['tmp_name'] );
				$outname = sanitize_file_name( $_FILES[$postedfile]['name'] );
				$type = 'file';
				$_SESSION['womshow'.$theme_abbrev.$postedname] = compact( 'data', 'outname', 'type' );
			}
			else if ( isset( $_SESSION['womshow'.$theme_abbrev.$postedname] ) )
				extract( $_SESSION['womshow'.$theme_abbrev.$postedname] );	 // sets $data, $outname, $type
			else $msg = __( 'Uploaded file is missing.', 'womjoy' );
		}
		else {
			unset( $_SESSION['womshow'.$theme_abbrev.$postedname] );
			if ( current_user_can( 'manage_options' ) ) {
				$theme_settings_name = $theme_abbrev.( $theme_abbrev=='weaver' ? '_main' : '' ).'_settings';
				$switcher = $theme_abbrev.'_switch_themes';
				list( $option, $suboption ) = explode( '+wom+', $_POST[$postedname], 2 );
				$option = sanitize_key( $option );
				$suboption = esc_html( $suboption );
				$valid_list = wp_list_pluck( wom_merge_get_options_from_db( $theme_settings_name ), 'option_name' );
				$valid_list[] = $switcher;
				if ( in_array( $option, $valid_list ) ) {
					if ( $suboption ) {
						$data = apply_filters( 'wom_merge_get_switch', get_option( $switcher, array() ), $switcher );
						if ( array_key_exists( $suboption, $data ) ) {
							$swkey = array( 'weaverii'=>'basic', 'aspen'=>'aspen_base', 'weaverx'=>'weaverx_base' );
							$pro = $theme_abbrev=='weaverii' ? $data[$suboption]['pro'] : array();
							$data = $data[$suboption][$swkey[$theme_abbrev]];
							$suboption = str_replace( ' ', '-', $suboption ).'_';
						}
						else {
							$msg = sprintf( __( 'Theme switcher option set %s is missing.', 'womjoy' ), $suboption );
							unset( $data );
						}
					}
					else {
						$data = get_option( $option, array() );
						if ( $theme_abbrev == 'weaver' )
							$data = $data + get_option( str_replace( '_main', '_advanced', $option ), array() );
						$pro = get_option( str_replace( $theme_settings_name, 'weaverii_pro', $option ), array() );
					}
					if ( $theme_abbrev == 'weaverx' )
						unset( $data['wvrx_css_saved'] );
					$outname = $suboption.$option;
					$type = 'option';
				}
			}
			if ( !isset( $data ) && !$msg ) {
				$theme_info = wp_get_theme( $theme_name );
				$upload_dir = wp_upload_dir();
				add_filter( 'wom_merge_file_ext', 'wom_merge_'.$theme_abbrev.'_file_ext');
				$valid_files = wom_merge_get_files_from_dir( wp_normalize_path( $theme_info->get_stylesheet_directory() ) );
				if ( current_user_can( 'manage_options' ) ) {
					array_splice( $valid_files, count( $valid_files ), 0, wom_merge_get_files_from_dir( wp_normalize_path( $upload_dir['basedir'] ).'/'.$theme_abbrev.'-subthemes' ) );
				}
				array_splice( $valid_files, count( $valid_files ), 0, wom_merge_get_files_from_dir( wp_normalize_path( plugin_dir_path( __FILE__ ) ).'optionsets' ) );
				remove_filter( 'wom_merge_file_ext', 'wom_merge_'.$theme_abbrev.'_file_ext');
				if ( in_array( $_POST[$postedname], $valid_files ) ) {
					$data = file_get_contents( $_POST[$postedname] );
					$outname = $_POST[$postedname];
					$type = 'file';
				}
				else $msg = __( 'Requested file is missing.', 'womjoy' );
			}
		}
		if ( !$msg && 'file' == $type ) {
			$output_type = wom_merge_file_identify( $theme_abbrev, substr( $data, 0, 10 ) );
			if ($output_type) {
				$options_arr = unserialize( substr( $data, 10 ) );
				if ( is_array( $options_arr ) ) {
					if ( $theme_abbrev == 'weaver' )
						$data = $options_arr;
					else
						$data = $options_arr[$theme_abbrev.'_base'];
					$pro = $theme_abbrev=='weaverii' ? $options_arr['weaverii_pro'] : array();
					$outname = basename( $outname );
				}
				else {
					$msg = sprintf( __( 'The unserialize failed on the file %s.', 'womjoy' ), $outname );
					unset( $data, $_SESSION['womshow'.$theme_abbrev.$postedname] );
				}
			}
			else {
				$msg = sprintf( _x( 'The file %1$s is not a %2$s settings file.', '1 is filename, 2 is theme name', 'womjoy' ), $outname, ucwords( $theme_name ) );
				unset( $data, $_SESSION['womshow'.$theme_abbrev.$postedname] );
			}
		}
		if ( isset( $pro ) && count( $pro ) )
			$data = array_merge( $data, $pro );
	}
	else $msg = __( 'Choose something to merge.', 'womjoy' );
	return compact( 'msg', 'outname', 'type', 'theme_abbrev', 'output_type', 'data', 'pro' );
} //wom_merge_load_option_set

// validate user inputs when Show button is clicked, save results in session
function wom_merge_show_input_validate() {
	if ( isset( $_POST['wompage'] ) && $_POST['wompage'] == 'wom_merge_show' ) {
		$theme_abbrev = wom_merge_abbrev( $_POST['womtheme'] );
		$prefix = 'wom_'.$theme_abbrev;
		check_admin_referer( $prefix.'showmerge', $prefix.'showmergeID' );
		if ( wom_merge_valid_theme( $_POST['womtheme'] ) ) {
			session_name( WOM_MERGE_SESSION );
			session_start();
			$base = wom_merge_load_option_set( $_POST['womtheme'], $prefix.'_optset_0', $prefix.'_settings_file0' );
			$overlay = wom_merge_load_option_set( $_POST['womtheme'], $prefix.'_optset_1', $prefix.'_settings_file1' );
			$_SESSION['womshow_base'] = $base;
			$_SESSION['womshow_overlay'] = $overlay;
			if ( !$base['msg'] && !$overlay['msg'] ) {
				$_SESSION['wommerge'.$theme_abbrev] = compact( 'base', 'overlay' );
			}
			session_write_close();
		}
	}
}

//---------------------------------------------------------------------------------
function wom_merge_admin_page() {
?>
<div class="wrap">
	<div id="icon-themes" class="icon32"><br /></div><h2><?php _e( 'Weaver Options Merge', 'womjoy' ); ?> - <?php _e( 'Version', 'womjoy' );?>  <?php echo WOM_MERGE_VERSION; ?></h2>
	<h3><?php _e( 'Merge two settings files from Weaver II, Aspen, Weaver Xtreme, or Weaver', 'womjoy' ); ?></h3>
	<p><?php _e( 'This plugin will take two sets of options, and merge them as you specify, and output a settings file.', 'womjoy' ); ?>
	<?php _e( 'It reads from, but does not write to the database, and it does not matter if the theme is active.', 'womjoy' ); ?></p>
	<hr />
	<div id="womaccordion">
		<h3>Weaver II</h3>
		<div id="tab1">
			<?php echo wom_merge_create_list_form( 'weaver-ii', 'weaverii' ); ?>
		</div>
		<h3>Aspen</h3>
		<div id="tab2">
			<?php	echo wom_merge_create_list_form( 'aspen', 'aspen' ); ?>
		</div>
		<h3>Weaver Xtreme</h3>
		<div id="tab3">
			<?php	echo wom_merge_create_list_form( 'weaver-xtreme', 'weaverx' ); ?>
		</div>
		<h3>Weaver</h3>
		<div id="tab4">
			<?php	echo wom_merge_create_list_form( 'weaver', 'weaver' ); ?>
		</div>
	</div>
	<hr />
	<p><a href="http://joyreynolds.com/"><?php _e( 'Check for more cool stuff from Joy.', 'womjoy' ); ?></a>
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
		<input type="hidden" name="cmd" value="_s-xclick">
		<input type="hidden" name="hosted_button_id" value="H28YDFRKNP828">
		<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal">
		<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
		</form>
	</p>
</div>
<?php
} //wom_merge_admin_page

//==============================================================
// output the options	file
function wom_merge_output_file( $fileID, $save, $filename ) {
	$output = $fileID . serialize($save);		// serialize full set of options

	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename='.$filename);
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');
	header('Content-Length: ' . strlen($output));
	echo $output;
} //wom_merge_output_file

//==============================================================
// prepare the Weaver II options file
function wom_merge_weaverii_output_file( $base_options, $pro_options, $output_name ) {
	$file_ext = 'w2t';
	if ( count( $pro_options ) )
		$file_ext = 'w2b';
	else {
		foreach ($base_options as $k=>$v) {
			if ($k[0] == '_') {
				$file_ext = 'w2b';
				break;
			}
		}
	}
	$fileID = strtoupper($file_ext).'-V01.00';
	$final['weaverii_base'] = $base_options;
	$final['weaverii_pro'] = $pro_options;
	wom_merge_output_file( $fileID, $final, $output_name .'.'. $file_ext );
} //wom_merge_weaverii_output_file

//==============================================================
// prepare the Aspen options file
function wom_merge_aspen_output_file( $base_options, $output_name ) {
	$file_ext = 'ath';
	foreach ($base_options as $k=>$v) {
		if ($k[0] == '_') {
			$file_ext = 'abu';
			break;
		}
	}
	$final['aspen_base'] = $base_options;
	$fileID = strtoupper($file_ext).'-V01.00';
	wom_merge_output_file( $fileID, $final, $output_name .'.'. $file_ext );
} //wom_merge_aspen_output_file

//==============================================================
// prepare the Weaver Xtreme options file
function wom_merge_weaverx_output_file( $base_options, $output_name ) {
	$file_ext = 'wxt';
	foreach ($base_options as $k=>$v) {
		if ($k[0] == '_') {
			$file_ext = 'wxb';
			break;
		}
	}
	$final['weaverx_base'] = $base_options;
	$fileID = strtoupper($file_ext).'-V01.00';
	wom_merge_output_file( $fileID, $final, $output_name .'.'. $file_ext );
} //wom_merge_weaverx_output_file

//==============================================================
// prepare the Weaver options file
function wom_merge_weaver_output_file( $base_options, $output_type, $output_name ) {
	unset( $base_options['ftp_hostname'], $base_options['ftp_username'], $base_options['ftp_password'] );
  if ($output_type == 'theme') {
		$file_ext = 'wvr';
		$fileID = 'TTW-V01.10';
	}
	else {
		$file_ext = 'wvb';
		$fileID = strtoupper($file_ext).'-V02.00';
	}
	wom_merge_output_file( $fileID, $base_options, $output_name .'.'. $file_ext );
} //wom_merge_weaver_output_file

//==============================================================
// split CSS --- utility for wom_merge_combine_css
function wom_merge_split_into_parts( $s ) {
	$s=preg_replace( '/\s/',"\x02", $s );		//change whitespace to hex02
	$s=preg_replace( array( '/{/', '/}/' ), array( "\t", "\t" ), $s, 1 );	//change first brace set to tabs
	$s2 = sscanf( $s, "%s\t%s\t%s" );	//parse into 3 pieces
	$s2 = str_replace( array( "\x02", "\t", '  ', '} ' ), array( ' ', '', ' ', "}\n" ), $s2 ); //change hex02 to space and tabs removed
	$s2 = array_map( 'trim', $s2 );
	return $s2;
} //wom_merge_split_into_parts

//==============================================================
// combine two CSS option values
function wom_merge_combine_css( $target, $other ) {
	$target = preg_replace( array( '/{\s*}/', '/{/', '/}/' ), array( ' { }', ' {', '} ' ), trim( $target ) );
	$other = preg_replace( array( '/{\s*}/', '/{/', '/}/' ), array( ' { }', ' {', '} ' ), trim( $other ) );
	if ( empty( $target ) || '{ }' == trim( $target ) )
		$target = $other;
	else if ( empty( $other ) || '{ }' == trim( $other ) ) {/* do nothing */}
	else {
		list( $tfirst, $tcss, $trest ) = wom_merge_split_into_parts( $target );
		list( $ofirst, $ocss, $orest ) = wom_merge_split_into_parts( $other );
		$ocss = rtrim( $ocss, ';' );
		$target = ($ofirst[0] == ',' ? ($tfirst . $ofirst) : ($ofirst . $tfirst)) .'{'. $ocss. ($ocss ? '; ' : '') . $tcss . "}\n" . $orest . $trest;
	}
	return $target;
} //wom_merge_combine_css

//==============================================================
// extract first part of CSS option values
function wom_merge_extract_css( $option ) {
	$option = preg_replace( array( '/{\s*}/', '/{/', '/}/' ), array( ' { }', ' {', '} ' ), trim( $option ) );
	if ( empty( $option ) || '{ }' == trim( $option ) )	 {/* do nothing */}
	else {
		list( $ofirst, $ocss, $orest ) = wom_merge_split_into_parts( $option );
		$option = $ofirst .'{'. rtrim( $ocss, ';' ) . "}";
	}
	return $option;
} //wom_merge_extract_css

//---------------------------------------------------------------------------------
// use the user $choices to copy or merge the $overlay array onto the $base array
function wom_merge_process( $base, $overlay, $choices ) {
	foreach( array_keys( $choices, '2' ) as $k ) {
		$base[$k] = $overlay[$k];
	}
	foreach( array_keys( $choices, '3' ) as $k ) {
		if ( substr( $k, -9 ) == 'color_css' )
			$base[$k] = wom_merge_combine_css( $base[$k], $overlay[$k] );
		else
			$base[$k] .= $overlay[$k];
	}
	foreach( array_keys( $choices, '0' ) as $k ) {
		unset( $base[$k] );
	}
	return $base;
} //wom_merge_process

function wom_merge_admin_process() {
	if(isset($_POST['wompage']) && $_POST['wompage'] == 'wom_merge_export' ) {
		$theme_abbrev = wom_merge_abbrev( $_POST['womtheme'] );
		check_admin_referer( 'wommergefile', $theme_abbrev.'mergeIDfile' );
		session_name( WOM_MERGE_SESSION );
		session_start();
		if ( isset( $_SESSION['wommerge'.$theme_abbrev] ) ) {
			extract( $_SESSION['wommerge'.$theme_abbrev] );
//			unset( $_SESSION['wommerge'.$theme_abbrev] );
			$choices = array_map( 'absint', ( isset( $_POST['womchoice'] ) ? $_POST['womchoice'] : array() ) );
			$merged = wom_merge_process( $base['data'], $overlay['data'], $choices );
			$basepath = pathinfo( $base['outname'] );
			$overlaypath = pathinfo( $overlay['outname'] );
			$outname = $basepath['filename'] . '_WOM_' . $overlaypath['filename']; // build name to show merged
			switch ( $theme_abbrev ) {
				case 'aspen':
					wom_merge_aspen_output_file( $merged, $outname );
					break;
				case 'weaver':
					$merge_type = ( $base['output_type'] == 'theme' && $overlay['output_type'] == 'theme' ) ? 'theme' : 'backup';
					wom_merge_weaver_output_file( $merged, $merge_type, $outname );	 //
					break;
				case 'weaverx':
					wom_merge_weaverx_output_file( $merged, $outname );
					break;
				case 'weaverii':
				default:
					$basic = array_diff_key( $merged, $base['pro'], $overlay['pro'] );
					$pro = array_diff_key( $merged, $basic );
					wom_merge_weaverii_output_file( $basic, $pro, $outname );
			}
		}
		session_write_close();
		exit;
	}
} //wom_merge_admin_process

function wom_merge_deactivate() {
	delete_transient( WOM_MERGE_FILES );
	session_name( WOM_MERGE_SESSION );
	session_start();
	unset( $_SESSION['wommergeweaverii'], $_SESSION['wommergeaspen'], $_SESSION['wommergeweaverx'], $_SESSION['wommergeweaver'] );
	if ( isset( $_COOKIE[WOM_MERGE_SESSION] ) ) {
		setcookie( WOM_MERGE_SESSION, session_id(), 1 );
	}
	session_write_close();
} //wom_merge_deactivate


//---------------------------------------------------------------------------------
//------ WordPress function, new in 3.9.0
if ( !function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '|/+|','/', $path );
		return $path;
	}
}