<?php
/**
 * Plugin Name: CS Multiple Image Import
 * Plugin URI: http://catchsquarethemes.com
 * Description: This plugins accept the list of images files in xml, xls, xlsx format and import the images define in the file wordpress direcotry structure
 * Version: 1.0
 * Author: Catch Square
 * License:     GNU General Public License v2.0 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Text Domain: wordpress-google-analytics
 *
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write
 * to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */
if ( ! defined( 'ABSPATH' ) )
    exit;

define('CSMII_VERSION','1.0');
define( 'CSMII_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'CSMII_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'CSMII_CSS_DIR', plugin_dir_url(__FILE__) . 'css/');
define( 'CSMII_JS_DIR', plugin_dir_url(__FILE__) . 'js/');
define('CSMII_UPLOAD_DIR',untrailingslashit( WP_CONTENT_DIR.'/uploads/csmii_upload/'));

if(!class_exists('CsMultipleImageImport')):
    class CsMultipleImageImport{

        /*
         * Initialize the plugins and registration hooks
         * loading the needed css and js files
         */

        public function __construct(){
            if (!session_id()) {
                session_start();
            }
            add_action('admin_menu',array($this,'csmii_add_admin_menu'));
            require_once( CSMII_PLUGIN_DIR . '/includes/classes/CSMIISpreadsheetReader.php' );
            add_action('admin_post_csmii_save_settings',array($this,'csmii_save_settings'));
            add_action('admin_post_csmii_save_images_import',array($this,'csmii_save_images_import'));
        }

        /*
         * function to add the custom menu in the admin interface
         * @since 1.0
         */

        public function csmii_add_admin_menu(){
            add_menu_page('CS Image Import', 'CS Image Import', 'manage_options', 'csmii-admin', array($this, 'csmii_landing_page'), 'dashicons-id');

        }

        /*
         * landing page function
         * @since 1.0
         */

        public function csmii_landing_page(){
            include('includes/admin/csmii_images_import_form.php');
        }

        /*
         * function to save the images or the url users posted
         * @since 1.0
         */

        public function csmii_save_images_import(){
            if (isset($_POST['csmii_add_nonce_save_images_import']) && wp_verify_nonce($_POST['csmii_add_nonce_save_images_import'], 'csmii_nonce_save_images_import')) {

                if(isset($_FILES['csmii_upload_file']) && $_FILES['csmii_upload_file']['size'] > 0){
                    $csmiiFiles = $_FILES['csmii_upload_file'];
                }
                else {
                    $csmiiFiles = null;
                }
                $this->import_form_validation($csmiiFiles);
                global $csmiiFilesToBeImported,$csmiiUrl;
                $csmiiFilesToBeImported = $csmiiFiles;

                $this->csmii_import_files();

            }
            else {
                die('No script kiddies please!');
            }
        }
        /*
         * function to validate the field of the images import function
         * @since 1.0
         */

        function import_form_validation($file)
        {
            global $csmi_import_errors,$csmiiUploadeFileType;
            $csmi_import_errors = new WP_Error;
            if(!$file){
                $csmi_import_errors->add('csmii_upload_file', 'Please provide the zip file to import.');
            }
            else if($file){
                $fileType = $file['type'];
                $csmiiUploadeFileType = $fileType;
                $supportedFileTypes = array('application/zip','application/x-zip-compressed');
                if(!in_array($fileType,$supportedFileTypes)){
                    if(isset($_SESSION['csmii_import_error_count'])){
                        $_SESSION['csmii_import_error_count'] = $_SESSION['csmii_import_error_count'] + 1;
                    }
                    else{
                        $_SESSION['csmii_import_error_count'] = 1;
                    }
                    $csmi_import_errors->add('csmii_upload_file', 'Only zip is supported.');
                }
            }

            if ( is_wp_error( $csmi_import_errors ) && count($csmi_import_errors->get_error_messages()) > 0 ) {
                $_SESSION['csmii_import_error'] = $csmi_import_errors;
                wp_redirect( admin_url() . 'admin.php?page=csmii-admin' );
            }
            return true;
        }

        /*
         * function to import the data or get the data from the url and start the importing of the file
         * @since 1.0
         */

        public function csmii_import_files(){
            global $csmi_import_errors,$csmiiFilesToBeImported,$csmiiUrl,$csmiiUploadeFileType,$csmiiFileName,$csmiiImportedFolder;
            set_time_limit(0);
            ini_set('memory_limit','1024M');
            if ( 1 > count( $csmi_import_errors->get_error_messages() ) ) {
                if($csmiiFilesToBeImported){
                    $fileExtensions = explode('.',$csmiiFilesToBeImported['name']);
                    $fileExtension = $fileExtensions[count($fileExtensions) - 1];
                    $desinationFolder = CSMII_UPLOAD_DIR;
                    $sourceFile = $csmiiFilesToBeImported['tmp_name'];
                    $uploadeFileName = time();
                    $csmiiImportedFolder = $uploadeFileName;
                    $csmiiFileName = $uploadeFileName.'.'.$fileExtension;
                    move_uploaded_file($sourceFile,$desinationFolder.$csmiiFileName);
                }

                /*
                 * handling the files to be imported
                 */
                if($csmiiUploadeFileType=="application/zip" || $csmiiUploadeFileType="application/x-zip-compressed"){
                    //handling the zip files
                    $this->csmii_export_zip_files();
                }
            }

        }

        /*
         * function that handles the import of the zip file type
         * @since 1.0
         */

        public function csmii_export_zip_files(){
            global $csmiiFileName,$csmiiImportedFolder;
            $zip = new ZipArchive;
            $res = $zip->open(CSMII_UPLOAD_DIR.$csmiiFileName);
            if($res==true){
                $zip->extractTo(CSMII_UPLOAD_DIR.$csmiiImportedFolder.'/');
                $zip->close();
//                $this->csmii_all_files(CSMII_UPLOAD_DIR.'imported-files/');
                $fileNames = [];
                $rootpath = CSMII_UPLOAD_DIR.$csmiiImportedFolder.'/';
                $fileinfos = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($rootpath)
                );
                foreach($fileinfos as $pathname => $fileinfo) {
                    if (!$fileinfo->isFile()) continue;
                    $fileNames[] = $pathname;
                }
                foreach($fileNames as $fn):
                    $mimeType = mime_content_type($fn);
                    if($mimeType=="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"){
                        $parentDirectory = dirname($fn);
                        $wordpress_upload_dir = wp_upload_dir();
                        $Reader = new CSMIISpreadsheetReader($fn);
                        $counter = 1;
                        foreach ($Reader as $Row)
                        {
                            if($counter!=1):
                                if($Row[0]!='' && file_exists($parentDirectory.'/'.$Row[0])):
                                    $new_file_path = $wordpress_upload_dir['path'] . '/' . $Row[0];
                                    $new_file_mime = mime_content_type( $parentDirectory.'/'.$Row[0] );
                                    copy($parentDirectory.'/'.$Row[0],$new_file_path);
                                    $upload_id = wp_insert_attachment( array(
                                        'guid'           => $new_file_path,
                                        'post_mime_type' => $new_file_mime,
                                        'post_title'     => (isset($Row[1]))?sanitize_text_field($Row[1]):preg_replace( '/\.[^.]+$/', '', $Row[0] ),
                                        'post_content'   => isset($Row[2])?sanitize_text_field($Row[2]):'',
                                        'post_status'    => 'inherit',
                                        'post_excerpt'   => isset($Row[4])?sanitize_text_field($Row[4]):''
                                    ), $new_file_path );
                                    require_once( ABSPATH . 'wp-admin/includes/image.php' );
                                    // Generate and save the attachment metas into the database
                                    wp_update_attachment_metadata( $upload_id, wp_generate_attachment_metadata( $upload_id, $new_file_path ) );
                                    update_post_meta( $upload_id, '_wp_attachment_image_alt', isset($Row[3])?sanitize_text_field($Row[3]):'image' );
                                endif;
                            endif;
                            $counter++;
                        }
                    }
                endforeach;
                    $this->csmii_remove_all_directory(CSMII_UPLOAD_DIR.$csmiiImportedFolder);
                    unlink(CSMII_UPLOAD_DIR.$csmiiFileName);
            }
            else{
                $_SESSION['csmii_file_error'] = '<span class="csmii_error">There seems to be some problem on extracting your zip file. Please try again later</span>' ;
                wp_redirect( admin_url() . 'admin.php?page=csmii-admin' );
            }
            $_SESSION['csmii_file_error'] = '<span class="csmii_success">Success!!!</span> Importing of the images completed successfully.';
            wp_redirect( admin_url() . 'admin.php?page=csmii-admin' );
        }

        /*
         * function to delete all the content of the extracted files
         * @since 1.0
         */
        public  function csmii_remove_all_directory($dir) {
            $structure = glob(rtrim($dir, "/").'/*');
            if (is_array($structure)) {
                foreach($structure as $file) {
                    if (is_dir($file)) $this->csmii_remove_all_directory($file);
                    elseif (is_file($file)) unlink($file);
                }
            }
            rmdir($dir);
        }

    }
endif;

$csmii_object = new CsMultipleImageImport();