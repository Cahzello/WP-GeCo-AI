<?php

/** 
 * Plugin Name: SMT GeCo AI
 * Plugin URI:
 * Description: GeCo AI is an innovative product by Generate Content designed to streamline article creation for WordPress users. Leveraging advanced AI technology, GeCo empowers writers by automating content generation, enabling them to effortlessly produce high-quality articles with ease and efficiency.
 * Version: 0.2.0
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Author: Rizky Rasya
 * Author URI: https://github.com/Cahzello
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: SMT-GeCo-AI
 * Domain Path: /languages
 *
 * SMT GeCo AI is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * SMT GeCo AI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SMT GeCo AI. If not, see <https://www.gnu.org/licenses/>.
 *
 */

// Include the Composer autoloader
require __DIR__ . '/vendor/autoload.php';
require 'function/generate_message.php';

use Spatie\Async;
use Spatie\Async\Pool;

// function for creating prompt messages
function generate_message($keyword, $bahasa, $paragraf)
{
    $message = [
        ['role' => 'system', 'content' => 'Do not include any explanations, only provide a  RFC8259 compliant JSON response, without Duplicate object key, following this format without deviation.'],
        ['role' => 'system', 'content' => 'JSON format used: { "Title": " ", "Meta": " ", "Content": " " }'],
        ['role' => 'system', 'content' => 'When creating a new paragrah use "\n\n" instead inserting a new line manually'],
    ];

    // add custom prompt if user input from the settings
    if (get_option('SMT_GeCo_AI_setting_prompt')) {
        $prompt = get_option('SMT_GeCo_AI_setting_prompt');

        $output = str_replace(['[keyword]', '[bahasa]'], [$keyword, $bahasa], $prompt);

        $custom_prompt = ['role' => 'user', 'content' => $output];

        $message[] = $custom_prompt;
    } else {

        $custom_prompt = ['role' => 'user', 'content' => 'Buatlah artikel dengan rincian yang komprehensif,' . $paragraf . ' paragraf, 1 paragraf mengandung 6 kalimat. Dengan berisikan judul, meta, dan konten yang unik dengan kata kunci ' . $keyword . '. Dan menggunakan bahasa: ' . $bahasa];

        $message[] = $custom_prompt;
    }

    return $message;
}

function generate_image($img)
{
    // get api key from wp db
    $yourApiKey = get_option('SMT_GeCo_AI_setting_api_key');

    // Initialize the OpenAI client and set the API key
    $client = OpenAI::client($yourApiKey);

    // operate the task
    if (isset($img)) {
        try {
            $img_response = $client->images()->create([
                'prompt' => $img,
                'n' => 1,
                'size' => '256x256',
                'response_format' => 'url',
            ]);
        } catch (Throwable $th) {
            // throw $th;
            add_action('admin_notices', function () use ($th) {
                echo "<div class='notice notice-info is-dismissible'><p> {$th} </p></div>";
            });
        }

        $image = $img_response->data[0]->url;

        return $image;
    }
}


// Define the function that generates content using the ChatGPT API
function generate_content($keyword, $bahasa, $paragraf)
{
    // get api key from wp db
    $yourApiKey = get_option('SMT_GeCo_AI_setting_api_key');

    // Initialize the OpenAI client and set the API key
    $client = OpenAI::client($yourApiKey);

    // Prepare the chat messages
    $messages = generate_message($keyword, $bahasa, $paragraf);

    // choose AI Models
    $model_field_value = get_option('SMT_GeCo_AI_setting_model', 'gpt-3.5-turbo');

    try {
        // create async call
        $pool = Pool::create();

        $pool
            ->add(function () use ($client, $messages, $model_field_value) {
                // code to async
                $response = $client->chat()->create([
                    'model' => $model_field_value,
                    'messages' => $messages,
                    'temperature' => 1.2,
                    'max_tokens' => 3000
                ]);

                return $response;
            })
            ->then(function ($output) use (&$response) {
                // On success, `$output` is returned by the process or callable you passed to the queue.
                $response = $output;
            })
            ->catch(function ($exception) {
                // When an exception is thrown from within a process, it's caught and passed here.
                // wp_die('Error Generating Response ' . $exception, 'Error');
                add_action('admin_notices', function () use ($exception) {
                    echo "<div class='notice notice-info is-dismissible'><p> {$exception} </p></div>";
                });
            });
        $pool->wait();

        // Extract the response content from the API response
        $hasil = $response->choices[0]->message->content;
        $jsonData = preg_replace('/[\x00-\x1F]/u', '', $hasil);
        $decodedJson = json_decode($jsonData, false, 512, JSON_THROW_ON_ERROR);

        // write log file
        $path_to_plugin = "../wp-content/plugins/SMT-GeCoAI/log/";
        $myfile = fopen($path_to_plugin . "response.json", "a") or die("Unable to open file!");
        $txt = $hasil;
        fwrite($myfile, PHP_EOL);
        fwrite($myfile, $txt);
        fclose($myfile);

        // handle error when the json response didn't match the schema
        if (!$hasil) {
            $actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            wp_die('Something Went Wrong, Please Refresh This Page Again. <a href="' . $actual_link . '">Refresh</a>', 'err_json');
        }
    } catch (Exception $e) {
        // Handle exceptions here or log the error for debugging
        wp_die("Error: Unable to generate content. Please try again later. Error message: <b> " . $e->getMessage() . "</b>", 'chatgpt err');
    }

    //return value
    return $decodedJson;
}

function make_post()
{
    // Check if API key is present or not (not checking is api key valid or not but if present or not)
    add_action('admin_notices', function () {
        if (!get_option('SMT_GeCo_AI_setting_api_key')) {
            $url = get_dashboard_url() . 'admin.php?page=SMT_GeCo_AI_custom_settings_page';
            echo '<div class="notice notice-warning is-dismissible">
                    <h3>SMT GeCo AI</h3>
                    <p>
                        You haven\'t put your API key in settings, go to plugin settings to put your API key.
                        <a href="' . $url . '">Go to settings</a>
                    </p>
                </div>';
        }
    });

    // Get the 'keyword' value from the URL
    $actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $url_components = parse_url($actual_link);

    // Check if the URL has query parameters
    if (isset($url_components['query'])) {
        parse_str($url_components['query'], $params);


        // Check if the 'keyword' parameter and api key is exists
        if (isset($params['keyword'])) {

            // check if bahasa parameter is exist or not, if not set lang default value to inggris
            if (!isset($params['bahasa'])) {
                $params['bahasa'] = "Indonesia";
            }

            // check if paragraf parameter is exist or not, if not set paragraf default value to empty string
            if (!isset($params['paragraf'])) {
                $params['paragraf'] = 2;
            }

            if (!get_option('SMT_GeCo_AI_setting_api_key')) {
                $url = get_dashboard_url() . 'admin.php?page=SMT_GeCo_AI_custom_settings_page';
                wp_die(
                    'Error: <b>No API key found</b>, go to setings to check your API key. <a href="' . $url . '">Go to settings.</a>',
                    'API key is not found'
                );
            }

            // create content
            $generated_content = generate_content($params['keyword'], $params['bahasa'], $params['paragraf']);

            // create image structure to be input to the post
            $image = generate_image($params['keyword']);
            $image_structure = "<img class='alignleft' src='" . $image . "' /> ";

            // insert data to array
            $post_data = array(
                'post_title'   => $generated_content->Title,
                'post_content' => $image_structure . $generated_content->Content,
                'post_status'  => 'draft', // 'publish', 'draft', 'pending', etc.
                'post_author'  => is_user_logged_in() ? get_current_user_id() : 0,
                'post_type'    => 'post', // 'post', 'page', or any custom post type you have
            );

            // check if the content and image is ready
            if ($generated_content->Title && $image_structure) {

                // submit data to wp_insert_post 
                $post_id = wp_insert_post($post_data);
            }

            // create notification after data is success insert
            if ($post_id) {
                $post_permalink = get_permalink($post_id);
                $notice = "Post created successfully. <a href='{$post_permalink}'>Go to post.</a>";
                add_action('admin_notices', function () use ($notice) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . $notice . '</p></div>';
                });
            } else {
                // An error occurred while creating the post
                // echo "Error creating the post.";
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-info is-dismissible"><p> Something went wrong : ( </p></div>';
                });
            }
        } else {
            // do nothing
        }
    } else {
        // do nothing
    }
}


// settings page
function SMT_GeCo_AI_settings_init()
{
    // register a new setting for the custom settings page
    register_setting('SMT_GeCo_AI_custom_settings_page', 'SMT_GeCo_AI_setting_api_key');
    register_setting('SMT_GeCo_AI_custom_settings_page', 'SMT_GeCo_AI_setting_model');
    register_setting('SMT_GeCo_AI_custom_settings_page', 'SMT_GeCo_AI_setting_prompt');

    // register a new section in the custom settings page
    add_settings_section(
        'SMT_GeCo_AI_settings_section',
        'SMT GeCo AI Settings Section',
        'SMT_GeCo_AI_settings_section_callback',
        'SMT_GeCo_AI_custom_settings_page'
    );

    // register a new field in the "SMT_GeCo_AI_settings_section" section, inside the custom settings page
    add_settings_field(
        'SMT_GeCo_AI_settings_field',
        'Open AI API Key',
        'SMT_GeCo_AI_settings_field_callback',
        'SMT_GeCo_AI_custom_settings_page',
        'SMT_GeCo_AI_settings_section'
    );

    add_settings_field(
        'SMT_GeCo_AI_model',
        'Model AI Used',
        'SMT_GeCo_AI_settings_field_model_callback',
        'SMT_GeCo_AI_custom_settings_page',
        'SMT_GeCo_AI_settings_section'
    );

    add_settings_field(
        'SMT_GeCo_AI_prompt',
        'Custom Prompt',
        'SMT_GeCo_AI_settings_field_prompt_callback',
        'SMT_GeCo_AI_custom_settings_page',
        'SMT_GeCo_AI_settings_section'
    );
}

/**
 * Add a custom settings page to the admin menu
 */
function SMT_GeCo_AI_add_custom_settings_page()
{
    add_menu_page(
        'SMT GeCo AI Settings Page',
        'SMT GeCo AI',
        'manage_options', // You can change the capability required to access this page
        'SMT_GeCo_AI_custom_settings_page', // Change this to a unique menu slug for your settings page
        'SMT_GeCo_AI_custom_settings_page_callback',
        'dashicons-format-aside', // Replace with your desired menu icon
    );
}

/**
 * Callback function for the custom settings page
 */
function SMT_GeCo_AI_custom_settings_page_callback()
{
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // Output the settings fields
            settings_fields('SMT_GeCo_AI_custom_settings_page');
            do_settings_sections('SMT_GeCo_AI_custom_settings_page');
            submit_button();
            ?>
        </form>
    </div>
<?php
}

/**
 * Register SMT_GeCo_AI_settings_init to the admin_init action hook
 */
add_action('admin_init', 'SMT_GeCo_AI_settings_init');

/**
 * Add the custom settings page to the admin menu
 */
add_action('admin_menu', 'SMT_GeCo_AI_add_custom_settings_page');

/**
 * Callback functions
 */

// Section content cb
function SMT_GeCo_AI_settings_section_callback()
{
    // echo '<h3>SMT GeCo AI settings for api key and AI model.</h3>';
    echo '';
}

// Field content cb
function SMT_GeCo_AI_settings_field_callback()
{
    // Get the value of the setting we've registered with register_setting()
    $setting = get_option('SMT_GeCo_AI_setting_api_key');
    // Output the field
?>
    <textarea cols="60" type="text" name="SMT_GeCo_AI_setting_api_key"><?php echo isset($setting) ? esc_attr($setting) : ''; ?>
    </textarea>
    <p>Put your OpenAI api key in text field above. <a href="https://platform.openai.com/account/api-keys">Click here to get api key.</a></p>

<?php
}

function SMT_GeCo_AI_settings_field_model_callback()
{
    $model_field_value = get_option('SMT_GeCo_AI_setting_model', 'gpt-3.5-turbo');
?>
    <label>
        <input type="radio" name="SMT_GeCo_AI_setting_model" value="gpt-3.5-turbo" <?php checked($model_field_value, 'gpt-3.5-turbo'); ?>>
        gpt-3.5-turbo
    </label>
    <br>
    <label>
        <input type="radio" name="SMT_GeCo_AI_setting_model" value="gpt-4" <?php checked($model_field_value, 'gpt-4'); ?>>
        gpt-4
    </label>
    <p>Select AI model to be use. <a href="https://platform.openai.com/docs/models/overview">Learn more.</a></p>

<?php
}

function SMT_GeCo_AI_settings_field_prompt_callback()
{
    $prompt = get_option('SMT_GeCo_AI_setting_prompt');
?>
    <textarea cols="65" type="text" name="SMT_GeCo_AI_setting_prompt"><?php echo isset($prompt) ? esc_attr($prompt) : ''; ?>
    </textarea>
    <p>Optional input if you want a unique case for prompt.</p>
    <p>the parameter available for use is:</p>
    <table border="1">
        <tr>
            <td><b>Paramater</b></td>
            <td><b>Fungtionality</b></td>
        </tr>
        <tr>
            <td>[keyword]</td>
            <td>What keyword to use <b>(Required)</b></td>
        </tr>
        <tr>
            <td>[bahasa]</td>
            <td>What language to use (optional, default value indonesian)</td>
        </tr>
        <tr>
            <td>[paragraf]</td>
            <td>How much paragraf to be added in article (optional, default value 2)</td>
        </tr>
    </table>
    <p>Example:</p>
    <p>"Buatkanlah saya artikel menggunakan bahasa [bahasa] dan artikelnya mengenai [keyword]. Dengan panjang paragraf [paragraf]"</p>
    <p>Note:</p>
    <p><b>Insert parameter without the value in parameter, so if you want use "[bahasa]" just put "[bahasa]".</b></p>
    <p>For the value in parameter will be inputed in address bar</p>
    <p>Example: ?keyword=Jakarta & bahasa=inggris & paragraf=5</p>

<?php
}

// run make_post function
add_action('admin_init', 'make_post');
