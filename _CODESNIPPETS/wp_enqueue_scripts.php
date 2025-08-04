<?php
/**
 * Enqueue scripts and styles for the theme
 *
 * This function is hooked to the 'wp_enqueue_scripts' action and is responsible for
 * registering and enqueuing various scripts and styles used in the theme.
 *
 * @package wp_enqueue_scripts
 * @version 1.0.0
 * @author Yamowo Claude
 * @license Proprietary
 *          このコードの再利用および再配布を禁止します。
 *          Reuse and redistribution of this code is prohibited.
 */
add_action('wp_enqueue_scripts', function() {
  
  /**
   * Get the version of a file based on its last modification time
   *
   * @param string $file The relative path to the file from the theme directory
   * @return int|bool The timestamp of the file's last modification, or false if the file doesn't exist
   */
  function get_file_version($file) {
    $file_path = get_stylesheet_directory() . $file;
    return file_exists($file_path) ? filemtime($file_path) : false;
  }
  
  // Use Swiper on all pages
  // wp_enqueue_script('swell_swiper');
  // wp_enqueue_style('swell_swiper');

  // Distributer
  // wp_deregister_script('jquery');  // Unregister jquery
  wp_enqueue_script('dist_gsap', 'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js', '', '', true);
  wp_enqueue_script('dist_gsap-st', 'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js', '', '', true);
  wp_enqueue_script('dist_lenis', get_stylesheet_directory_uri() . '/dev/js/lenis/lenis-1.0.19.min.js', [], '1.0.19', true);
  // wp_enqueue_script('youtube-api', 'https://www.youtube.com/iframe_api', [], null, true);
  
  
  // Development
  wp_enqueue_style('dev_main', get_stylesheet_directory_uri() .'/dev/css/dev-main.min.css', [], get_file_version('/dev/css/dev-main.min.css'));
  wp_enqueue_script('dev_main', get_stylesheet_directory_uri() . '/dev/js/main.min.js', [], get_file_version('/dev/js/main.min.js'), true);

  // for Top page
  if (is_home() || is_front_page()) {
    wp_enqueue_style('dev_home', get_stylesheet_directory_uri() .'/dev/css/home.min.css', [], get_file_version('/dev/css/home.min.css'));
    wp_enqueue_script('dev_set_mv', get_stylesheet_directory_uri() . '/dev/js/set_mv.min.js', [], get_file_version('/dev/js/set_mv.min.js'), true);
  }

  // for Fixed page
  // if (is_page() && !is_front_page()) {
  //   wp_enqueue_style('dev_page', get_stylesheet_directory_uri() .'/dev/css/page.min.css', [], get_file_version('/dev/css/page.min.css'));
  // }

  // for Post page
  // if (is_single()) {
  //   wp_enqueue_style('dev_single', get_stylesheet_directory_uri() .'/dev/css/single.min.css', [], get_file_version('/dev/css/single.min.css'));
  // }
      
}, 11);