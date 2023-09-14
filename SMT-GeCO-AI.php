<?php

/** 
 * Plugin Name: SMT GeCo AI
 * Plugin URI:
 * Description: GeCo AI is an innovative product by Generate Content designed to streamline article creation for WordPress users. Leveraging advanced AI technology, GeCo empowers writers by automating content generation, enabling them to effortlessly produce high-quality articles with ease and efficiency.
 * Version: 0.2.1
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Author: Rizky Rasya
 * Author URI: https://github.com/Cahzello
 * License: 
 * License URI:
 * Text Domain: SMT-GeCo-AI
 * Domain Path: /languages
 *
 *
 */

// Include the Composer autoloader

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/functions/image_generate.php';
require __DIR__ . '/functions/generate_message.php';

use Spatie\Async\Pool;

include_once('hook.php');

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

    $token = get_option('SMT_GeCo_AI_setting_token', 1000);
    $int_token = intval($token);

    try {
        // create async call
        $pool = Pool::create();

        $pool
            ->add(function () use ($client, $messages, $model_field_value, $int_token) {
                // code to async
                $response = $client->chat()->create([
                    'model' => $model_field_value,
                    'messages' => $messages,
                    'temperature' => 1.2,
                    'max_tokens' => $int_token,
                ]);
                return $response;
            })
            ->then(function ($output) use (&$response) {
                // On success, `$output` is returned by the process or callable you passed to the queue.
                $response = $output;
            })
            ->catch(function ($exception) {
                // When an exception is thrown from within a process, it's caught and passed here.
                add_action('admin_notices', function () use ($exception) {
                    echo "<div class='notice notice-error is-dismissible'><p> {$exception} </p></div>";
                });
            });
        $pool->wait();

        // Extract the response content from the API response

        $hasil = $response->choices[0]->message->content;
        $jsonData = preg_replace('/[\x00-\x1F]/u', '', $hasil);
        $decodedJson = json_decode($jsonData, false, 4096);

        // write log file
        $debug = false;
        if ($debug) {
            $path_to_plugin = plugin_dir_path(__FILE__) . "log";
            $myfile = fopen($path_to_plugin . "response.json", "a") or die("Unable to open file!");
            $txt = $hasil;
            fwrite($myfile, PHP_EOL);
            fwrite($myfile, $txt);
            fclose($myfile);
        }

        // handle error when the json response didn't match the schema
        if (!$hasil) {
            $actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            wp_die('Something Went Wrong, Please Refresh This Page Again. <a href="' . $actual_link . '">Refresh</a>', 'err_json');
        }
    } catch (Exception $e) {
        // Handle exceptions here or log the error for debugging
        // wp_die("Error: Unable to generate content. Please try again later. Error message: <b> " . $e->getMessage() . "</b>", 'chatgpt err');
        $actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        wp_die('Something Went Wrong, Please Refresh This Page Again. <a href="' . $actual_link . '">Refresh</a>', 'err_json');
    }

    //return value
    return $decodedJson;
}

function make_shortcode()
{
    $pages = get_pages();

    // The ID of the page where you want to add the shortcode.
    $page_id = $pages[0]->ID; // Replace with your page ID.

    // Get the content of the page using its ID.
    $page_content = get_post_field('post_content', $page_id);

    // Add the shortcode to the page content.
    $page_content_with_shortcode = do_shortcode('[smt_slider slider=2]') . $page_content;

    // Update the page content with the shortcode.
    $update_post_args = array(
        'ID'           => $page_id,
        'post_content' => $page_content_with_shortcode,
    );

    $result = wp_update_post($update_post_args);

    if (is_wp_error($result)) {
        // Handle the error here.
        $err_msg = $result->get_error_message();
        add_action('admin_notices', function () use ($err_msg) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($err_msg) . '</a> </p></div>';
        });

    } else {
        // Update was successful.
        add_action('admin_notices', function () {
            echo '<div class="notice notice-info is-dismissible"><p> Shortcode added successfully. </a> </p></div>';
        });
        
    }
}

function make_post()
{
    // Get the 'keyword' value from the URL
    $actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $url_components = parse_url($actual_link);

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

            // stop user from generating content where api key is not present
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
            $image = image_generate($params['keyword']);
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
