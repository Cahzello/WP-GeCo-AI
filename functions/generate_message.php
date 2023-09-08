<?php

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

        $custom_prompt = ['role' => 'user', 'content' => 'Buatlah artikel dengan rincian yang komprehensif, sebanyak' . $paragraf . ' paragraf, 1 paragraf mengandung 6 kalimat. Dengan berisikan judul, meta, dan konten yang unik dengan kata kunci ' . $keyword . '. Dan menggunakan bahasa: ' . $bahasa];

        $message[] = $custom_prompt;
    }

    return $message;
}