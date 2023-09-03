<?php

/**
 * Register SMT_GeCo_AI_settings_init to the admin_init action hook
 */
add_action('admin_init', 'SMT_GeCo_AI_settings_init');

/**
 * Add the custom settings page to the admin menu
 */
add_action('admin_menu', 'SMT_GeCo_AI_add_custom_settings_page');

/**
 * Run make_post function to create post
 */
add_action('admin_init', 'make_post');

// settings page
function SMT_GeCo_AI_settings_init()
{
    // register a new setting for the custom settings page
    register_setting('SMT_GeCo_AI_custom_settings_page', 'SMT_GeCo_AI_setting_api_key');
    register_setting('SMT_GeCo_AI_custom_settings_page', 'SMT_GeCo_AI_setting_model');
    register_setting('SMT_GeCo_AI_custom_settings_page', 'SMT_GeCo_AI_setting_img_size');
    register_setting('SMT_GeCo_AI_custom_settings_page', 'SMT_GeCo_AI_setting_token');
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
        'SMT_GeCo_AI_img_size',
        'Set Image Size',
        'SMT_GeCo_AI_settings_img_size_callback',
        'SMT_GeCo_AI_custom_settings_page',
        'SMT_GeCo_AI_settings_section'
    );

    add_settings_field(
        'SMT_GeCo_AI_token',
        'Set Max Token',
        'SMT_GeCo_AI_settings_token_callback',
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
        '', // You can change the capability required to access this page
        'SMTGeCoAI', // Change this to a unique menu slug for your settings page
        'SMT_GeCo_AI_custom_settings_page_callback',
        'dashicons-format-aside', // Replace with your desired menu icon
    );

    add_submenu_page(
        'SMTGeCoAI',
        'Settings',
        'Settings',
        'manage_options',
        'settings',
        'SMT_GeCo_AI_custom_settings_page_callback'
    );

    add_submenu_page(
        '',
        'Loading Page',
        'Loading',
        'manage_options',
        'loading',
        'loading_page'
    );
}

function loading_page()
{
    include_once('admin/loading.php');
}

/**
 * Callback function for the custom settings page
 */
function SMT_GeCo_AI_custom_settings_page_callback()
{
?>
    <div class="wrap">
        <h1>Settings Page</h1>
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
    <textarea require cols="60" type="text" name="SMT_GeCo_AI_setting_api_key"><?php echo isset($setting) ? esc_attr($setting) : ''; ?>
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

function SMT_GeCo_AI_settings_img_size_callback()
{
    $img_size = get_option('SMT_GeCo_AI_setting_img_size', '512x512');
?>
    <label>
        <input type="radio" name="SMT_GeCo_AI_setting_img_size" value="256x256" <?php checked($img_size, '256x256'); ?>>
        256x256 pixels
    </label>
    <br>
    <label>
        <input type="radio" name="SMT_GeCo_AI_setting_img_size" value="512x512" <?php checked($img_size, '512x512'); ?>>
        512x512 pixels
    </label>
    <label>
        <input type="radio" name="SMT_GeCo_AI_setting_img_size" value="1024x1024" <?php checked($img_size, '1024x1024'); ?>>
        1024x1024 pixels
    </label>
    <p>Choose image size to use</p>
    <p>If this field not set, default value will be <b>512x512</b> pixels</p>
    <p><?php echo get_option('SMT_GeCo_AI_setting_img_size', '512x512'); ?></p>
<?php
}

function SMT_GeCo_AI_settings_token_callback()
{
    $token = get_option('SMT_GeCo_AI_setting_token', 1000);
?>
    <input type="number" name="SMT_GeCo_AI_setting_token" placeholder="Ex: 1000" value="<?php echo isset($token) ? esc_attr($token) : ''; ?>">
    <p>Select how much token want to be used, <a href="https://platform.openai.com/docs/introduction/tokens">Learn More</a></p>
    <p>If this field not set, default value will be: <b>1000</b></p>
    <p>Note: <b>Maximal token for each model is different, please refer this <a href="https://platform.openai.com/docs/models/gpt-4">guide.</a></b></p>
<?php
}

function SMT_GeCo_AI_settings_field_prompt_callback()
{
    $placeholder = "Ex: Please make me an article with the keyword [keyword] and using [bahasa] language with minimun paraghraph is [paragraf].";
    $prompt = get_option('SMT_GeCo_AI_setting_prompt');
?>
    <textarea cols="65" rows="4" type="text" name="SMT_GeCo_AI_setting_prompt" placeholder="<?php echo $placeholder ?>"><?php echo isset($prompt) ? esc_attr($prompt) : ''; ?></textarea>
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
    <i>
        <p>"Buatkanlah saya artikel menggunakan bahasa [bahasa] dan artikelnya mengenai [keyword]. Dengan panjang paragraf [paragraf]"</p>
    </i>
    <p>Note:</p>
    <p><b>Insert parameter without the value in parameter, so if you want use "[bahasa]" just put "[bahasa]".</b></p>
    <p>For the value in parameter will be inputed in address bar</p>
    <p>Example: ?keyword=Jakarta & bahasa=inggris & paragraf=5</p>

<?php
}
