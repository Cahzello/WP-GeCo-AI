<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

$apikey = get_option('SMT_GeCo_AI_setting_api_key');
$client = OpenAI::client($apikey);

$size = get_option('SMT_GeCo_AI_setting_img_size', '512x512');

function image_generate($keyword)
{
    global $client;

    $response = $client->images()->create([
        'prompt' => $keyword,
        'n' => 1,
        'size' => '512x512',
        'response_format' => 'b64_json',
    ]);
    
    if (isset($response->data[0]->b64_json)) {
        // Decode the base64 image data
        $binary_image_data = base64_decode($response->data[0]->b64_json);

        // Load GD resource from binary data
        $im = imageCreateFromString($binary_image_data);

        // Make sure that the GD library was able to load the image
        if ($im) {
            // Specify the location where you want to save the image
            $upload_dir = wp_upload_dir();
            date_default_timezone_set('GMT');
            $img_file = $upload_dir['path'] . '/' . date("dmy_His") . ".png";

            // Save the GD resource as PNG in the best possible quality (no compression)
            // This will strip any metadata or invalid contents (including, the PHP backdoor)
            // To block any possible exploits, consider increasing the compression level
            imagepng($im, $img_file, 0);

            
            // Output the uploaded image URL
            $uploaded_image_url = $upload_dir['url'] . '/' . basename($img_file);

            // upload image to wordpress image library
            upload_image($uploaded_image_url);

            return $uploaded_image_url;
        } else {
            return 'Failed to create GD resource from base64 data.';
        }
    } else {
        return 'API response error or invalid base64 data.';
    }
}

/**
 * Uploads an image to the WordPress media library and generates its metadata.
 *
 * @param string $imgurl The URL of the image to be uploaded.
 */
function upload_image($imgurl)
{
    $image_url = $imgurl;

    $upload_dir = wp_upload_dir();

    $image_data = file_get_contents($image_url);

    $filename = basename($image_url);

    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null);

    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attach_id = wp_insert_attachment($attachment, $file);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);
}
