<?php 
if ( ! defined( 'ABSPATH' ) )
    exit;
?>
<div class="cs-wrap">
    <h1>CS Image Import</h1>
    <?php
    if( isset( $_SESSION['csmii_import_error'] ) ) {
        $csmi_import_error =  $_SESSION['csmii_import_error'];
        unset( $_SESSION['csmii_import_error'] );
    }
    else{
        $csmi_import_error = null;
    }
    if(isset($_SESSION['csmii_file_error'])){
        echo '<p>'.$_SESSION['csmii_file_error'].'</p>';
        unset($_SESSION['csmii_file_error']);
    }
    ?>
    <p>Click <a target="_blank" href="https://www.catchsquare.com/plugins_downloads/upload.zip">here</a> to download the sample file</p>
    <?php 
        if(isset($_SESSION['csmii_import_error_count']) && $_SESSION['csmii_import_error_count'] > 3){
            echo "<p style='color: #ff0000;'>The error may have occurred due to the max_upload_filesize and post_max_size configured in your php.ini files.</p>";
            unset($_SESSION['csmii_import_error_count']);
        }
    ?>
    <form action="<?php echo admin_url() . 'admin-post.php' ?>" method='post' class="csmii-image-import-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="csmii_save_images_import" />
        <?php wp_nonce_field( 'csmii_nonce_save_images_import', 'csmii_add_nonce_save_images_import' ); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Upload a file</th>
                <td>
                    <input type="file" name="csmii_upload_file" class="form-control" />
                    <?php echo ($csmi_import_error && $csmi_import_error->get_error_messages('csmii_upload_file'))?'<br /><br /><span class="csmii_error">'.$csmi_import_error->get_error_messages('csmii_upload_file')[0].'</span>':''; ?>
                    <p>You can upload a zip file containing xls,xlsx, with images containing in the zip or</p>
                    <p>Please find the sample file type in the link above, the file field should match as it is in the sample file</p>
                </td>
            </tr>
        </table>
        <div class="csmii-actions">
            <?php echo submit_button(); ?>
        </div>
    </form>
</div>

<style type="text/css">
    span.csmii_error {
        color: #ff0000;
        font-weight: bold;
    }

/*    .cs-wrap p {
        font-weight: bold;
        font-size: 18px;
        color: green;
    }*/

    span.csmii_success {
        font-size: 15px;
        color: green;
        font-weight: bold;
    }
</style>