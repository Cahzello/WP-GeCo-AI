jQuery(document).ready(function ($) {
    // Get the URL parameter 'show_element' from the URL.
    const urlParams = new URLSearchParams(window.location.search);
    const showElement = urlParams.get('show');

    // Check if the 'show_element' parameter is present and set to 'true'.
    if (showElement === 'true') {
        // If the parameter is present, show the custom element.
        $('#custom-element').show();
        alert("test");
    }
});
