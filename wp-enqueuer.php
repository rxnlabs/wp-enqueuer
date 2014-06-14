<?php
/*
Plugin Name: WP Enqueuer
Plugin URI: https://github.com/rxnlabs/wp-enqueuer
Description: WordPress plugin to enqueue commonly used javascript and css
Version: 1.0a
Author: De'Yonte W.
Author URI: http://rxnlabs.com
License:
Copyright 2014 De'Yonte W.
 
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.
 
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
 
  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA 
*/

// File Security Check
if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) && basename( __FILE__ ) == basename( $_SERVER['SCRIPT_FILENAME'] ) ) {
  die ( 'You do not have sufficient permissions to access this page!' );
}

// Only create an instance of the plugin if it doesn't already exists in GLOBALS
if( ! array_key_exists( 'wp-enqueuer', $GLOBALS ) ) {
  
  /**
   * @package WordPress\Plugins
   */
  class WP_Enqueuer {
    
    /**
     * File name that stores available scripts.
     *
     * @var string
     */
    private $script_file;

    /**
     * Store plugin version.
     *
     * @var string
     */
    private $version;

    /**
     * Store scripts and styles the plugin loads.
     *
     * @var array
     */
    private $loaded_scripts;
    private $loaded_styles;

    /**
     * Prefix for all keys in the plugin.
     *
     * @var string
     */
    private $prefix;

    /**
     * Prefix for all html names in plugin.
     *
     * @var string
     */
    public $dash_prefix;

    /**
     * Plugin constructor.
     *
     * Set class properties used throughout the class and call necessary methods.
     *
     * @var void
     */
    public function __construct() {
      $this->script_file = 'wp-enqueuer-scripts.json';
      $this->version = '1.0a';
      $this->prefix = 'wp_enqueuer_';
      $this->dash_prefix = 'wp-enqueuer';
      //hold list of enqueued scripts so we don't enqueue them twice
      $this->loaded_scripts = array();
      $this->loaded_styles = array();
      //load WordPress hooks
      if( is_admin() )
        $this->admin_hooks();
      else
        $this->front_hooks();
    }

    /**
     * Plugin hooks to be used in WordPress admin section.
     *
     * Attach the class methods that are called when WordPress does these actions in dashboard.
     *
     * @return void
     */
    public function admin_hooks(){
      add_action( 'admin_menu', array(&$this,'settings_page') );
      add_action( 'admin_enqueue_scripts', array(&$this,'admin_enqueue') );
      add_action( 'admin_init', array( &$this, 'set_enqueue' ) );
      add_action( 'admin_head', array( &$this, 'admin_enqueue_scripts_after' ) );
    }

    /**
     * Plugin hooks to be used in WordPress when not in WordPress dashboard.
     *
     * Attach the class methods that are called when WordPress does these actions when NOT in dashboard.
     *
     * @return void
     */
    public function front_hooks(){
      add_action( 'wp_enqueue_scripts', array(&$this,'front_enqueue_assets') );
    }

    public function install(){

    }

    /**
     * Get the content of json file that contains all scripts.
     *
     * The content of the json file that holds the name and location of all scripts we can load with plugin.
     *
     * @return object|false Decoded json object with scripts to load or false if file not found.
     */
    public function get_assets_file(){
      $assets_file = file_get_contents($this->get_assets_file_path());

      if( $assets_file != false )
        $assets_file = json_decode($assets_file);
      else
        $assets_file = false;

      return $assets_file;
    }

    /**
     * Get path to json file that contains all scripts.
     *
     * The path to the json file that holds the name and location of all scripts we can load with plugin.
     *
     * @return string A string representing a file path.
     */
    public function get_assets_file_path(){
      if ( (substr(plugin_dir_path( __FILE__ ), -1) == '/') OR (substr(plugin_dir_path( __FILE__ ), -1) == '\\') ){
        $plugin_path = substr_replace(plugin_dir_path( __FILE__ ),"",-1);
      }
      else{
        $plugin_path = plugin_dir_path( __FILE__ );
      }

      $assets_file_path = $plugin_path.DIRECTORY_SEPARATOR.$this->script_file;

      return $assets_file_path;
    }
    
    /**
     * Get the names of all post types registered.
     *
     * Get the name of all the public post types registered with WordPress.
     *
     * @return array An array of public post type names.
     */
    public function get_post_types(){
      $public_post_types = get_post_types(array('public'=>'true'),'names');

      // post types to not enqueue scripts for
      $remove_posts = array('attachment');

      // remove certain post types
      foreach ($public_post_types as $value) {
        foreach ($remove_posts as $remove) {
          if( array_key_exists($remove, $public_post_types))
            unset($public_post_types[$remove]);
        }
      }

      return $public_post_types;
    }

    /**
     * Get the label of a post type.
     *
     * @param string $post_type Name of a post type.
     * @return string An array of public post type names.
     */
    public function get_post_types_label($post_type = ''){
      if( !empty($post_type) ){
        $obj = get_post_type_object($post_type);
        $name = $obj->labels->name;
      }
      else{
        $objs = $this->get_post_types();
        $name = array();
        foreach( $objs as $obj ){
          $name[] = $obj->labels->name;
        }
      }

      $name = (empty($name)?false:$name);

      return $name;
    }

    /**
     * Save the scripts to load.
     *
     * Save the names of the scripts the user wanted to load. Save to database in the options table.
     *
     * @return void
     */
    public function set_enqueue(){
      if( $_GET['page'] === "wp-enqueuer-page.php" ){
        if( isset($_POST) AND !empty($_POST) AND array_filter($_POST) != false AND is_admin() AND check_admin_referer('wp_enqueuer_save_settings','wp_enqueuer_settings') ){
          // don't autosave
          if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

          // if user can't manage options
          if( !current_user_can( 'manage_options' ) ) return;

          $assets = json_decode(json_encode($this->get_assets_file()),true);
          // save the scripts to be enqueued based on the post type
          $post_types = $this->get_post_types();
          if( !empty($post_types) ){
            $set_enqueue = array();
            foreach( $post_types as $key=>$value ){
              $wp_enqueuer = $_POST[$this->prefix.$key];
              $load_in_footer = $_POST[$this->prefix.$key.'_footer'];
              $load_deps = $_POST[$this->prefix.$key.'_deps'];

              if( !empty($wp_enqueuer) ){

                foreach( $wp_enqueuer as $script_name ){
                  $asset_details = explode('_', $script_name);
                  $length = count($asset_details)-1;
                  $type = $asset_details[$length];
                  // remove the asset type from the asset details array
                  unset($asset_details[$length]);
                  $script_name = implode('_',$asset_details);
                  $this->loaded_scripts[$script_name] = array(
                      'deps'=>(!empty($load_deps) AND in_array($script_name,$load_deps)?true:false),
                      'type'=>$type
                    );

                  if( !empty($load_in_footer) AND in_array($script_name,$load_in_footer ) )
                    $this->loaded_scripts[$script_name]['footer'] = true;
                }
                
              }

              $result = update_option( $this->prefix.$key, (!empty($this->loaded_scripts)?$this->loaded_scripts:null) );
            }
          }
          return $this->loaded_scripts;
        }
      }
    }

    /**
     * Get the name of scripts that should load when post type is viewed.
     *
     *@param string $post_type Optional. Name of the post type.
     *@return array|false The names of scripts to load or false if no scripts are loading.
     */
    public function get_enqueued_scripts($post_type = ""){
      $post_types = $this->get_post_types();
      $scripts = false;
      if( empty($post_type) ){
        $scripts = array();
        foreach( $post_types as $key=>$value ){
          if ( get_option( $this->prefix.$key ) !== false ) {
            $scripts[$this->prefix.$key] = get_option( $this->prefix.$key );
          }
        }
      }else{
        if ( get_option( $this->prefix.$post_type ) !== false ) {
          $scripts[$this->prefix.$post_type] = get_option( $this->prefix.$post_type );
        }
      }

      return $scripts;
    }

    /**
     * Get the scripts the user wanted to load.
     *
     * Loop through list of scripts the user selected to download, return multidimensional associative array.
     *
     * @return array|false multidimensional associative array or false if no scripts selected.
     */
    public function get_enqueue_unique(){
      $post_types = $this->get_post_types();

      $scripts = false;
      if( !empty($post_types) AND is_null($post_type) ){
        $scripts = array();
        foreach( $post_types as $key=>$value ){
          if ( get_option( $this->prefix.$key ) !== false AND !empty(get_option( $this->prefix.$key )) ) {
            $scripts[$this->prefix.$key] = get_option( $this->prefix.$key );
            
            $count = 0;
            //loop through array to merge dependencies into one array
            foreach( $scripts[$this->prefix.$key] as $deps ){
              if( is_array($deps) ){
                foreach($deps as $dep){
                  $scripts[$this->prefix.$key][] = $dep;
                }
                //remove dependencies we just merged
                unset($scripts[$this->prefix.$key][$count]);
              }
              $count++;
            }

            //remove duplicate array values
            $scripts[$this->prefix.$key] = array_values(array_unique($scripts[$this->prefix.$key]));
          }
        }
      }

      return $scripts;
    }

    /**
     * Get the names of scripts that load in the footer.
     *
     * If the user selected the script to load in the footer, get the script name.
     *
     * @return array|bool multidimensional associative array or false if no scripts are loading in the footer.
     */
    public function get_enqueue_footer(){
      $post_types = $this->get_post_types();

      $scripts = false;
      if( !empty($post_types) AND is_null($post_type) ){
        $scripts = array();
        foreach( $post_types as $key=>$value ){
          if ( get_option( $this->prefix.$key.'_footer' ) !== false AND !empty(get_option( $this->prefix.$key.'_footer' )) ) {
            $scripts[$this->prefix.$key.'_footer'] = get_option( $this->prefix.$key.'_footer' );
          }
        }
      }

      return $scripts;
    }

    /**
     * Add settings page to WordPress admin menu.
     *
     * @return void
     */
    public function settings_page(){
      add_options_page( 'WP Enqueuer Settings', 'WP Enqueuer', 'manage_options', 'wp-enqueuer-page.php', array(&$this,'load_settings_page') );
    }

    /**
     * Load the plugin settings page.
     *
     * Load the settings page in the admin side of WordPress.
     *
     * @return void
     */
    public function load_settings_page(){
      include( 'wp-enqueuer-page.php' );
    }

    /**
     * Load the plugin javascript and css in WordPress admin.
     *
     * @param string $hook URL of current page in the WordPress dashboard.
     * @return void
     */
    public function admin_enqueue($hook){
      if( 'settings_page_wp-enqueuer-page' === $hook ){
        $this->admin_enqueue_scripts();
        $this->admin_enqueue_styles();
      }
    }

    /**
     * Enqueue the plugin javascript to load in WordPress admin.
     *
     * @return void
     */
    public function admin_enqueue_scripts(){
      global $wp_scripts;
      // register scripts
      wp_register_script( 'bootstrap-collapse', plugin_dir_url( __FILE__ ).'library/js/bootstrap/js/bootstrap.min.js' , array('jquery'), '3.1.1' );
      wp_register_script( 'checkboxes.js', plugin_dir_url( __FILE__ ).'library/js/jquery.checkboxes-1.0.3.min.js' , array('jquery'), '1.0.3' );
      wp_register_script( 'footable', plugin_dir_url( __FILE__ ).'library/js/footable/footable.min.js' , array('jquery'), '0.1.0' );
      wp_register_script( 'footable-sortable', plugin_dir_url( __FILE__ ).'library/js/footable/footable.sortable.min.js' , array('jquery','footable'), '0.1.0' );
      wp_register_script( 'footable-filter', plugin_dir_url( __FILE__ ).'library/js/footable/footable.filter.min.js' , array('jquery','footable'), '0.1.0' );
      wp_register_script( 'footable-paginate', plugin_dir_url( __FILE__ ).'library/js/footable/footable.paginate.js' , array('jquery','footable'), '0.1.0' );
      wp_register_script( 'datatables', 'http://cdn.datatables.net/1.9.4/js/jquery.dataTables.min.js' , array('jquery'), '1.9.4' );
      wp_register_script( 'datatables-bootstrap',  plugin_dir_url( __FILE__ ).'library/js/dataTables.bootstrap.js' , array('jquery','datatables'), '1.1.1' );
      wp_register_script( 'datatables-responsive', plugin_dir_url( __FILE__ ).'library/js/datatables-responsive/files/1/js/datatables.responsive.js' , array('jquery','datatables'), '1.10.0' );
      wp_register_script( 'responsive-tabs', plugin_dir_url( __FILE__ ).'library/js/responsive-tabs/js/jquery.responsiveTabs.min.js' , array('jquery'), '1.3.3' );
      wp_register_script( 'wp-enqueuer-scripts', plugin_dir_url( __FILE__ ).'library/js/wp-enqueuer-scripts.js' , array('jquery'), $this->version );

      // enqueue scripts
      wp_enqueue_script( 'bootstrap-collapse' );
      wp_enqueue_script( 'checkboxes.js' );
      //wp_enqueue_script( 'footable' );
      //wp_enqueue_script( 'footable-paginate' );
      //wp_enqueue_script( 'footable-sortable' );
      //wp_enqueue_script( 'footable-filter' );
      wp_enqueue_script( 'datatables' );
      //wp_enqueue_script( 'datatables-bootstrap' );
      wp_enqueue_script( 'datatables-responsive' );
      wp_enqueue_script( 'responsive-tabs' );
      wp_enqueue_script( 'wp-enqueuer-scripts' );
    }

    /**
     * Enqueue the plugin stylesheets to load in WordPress admin.
     *
     * @return void
     */
    public function admin_enqueue_styles(){
      // register styles
      wp_register_style( 'bootstrap-collapse', plugin_dir_url( __FILE__ ).'library/js/bootstrap/css/bootstrap.css' , false, '3.1.1' );
      //wp_register_style( 'footable', plugin_dir_url( __FILE__ ).'library/js/footable/footable.min.css' , false, '0.1.0' );
      //wp_register_style( 'footable-sortable', plugin_dir_url( __FILE__ ).'library/js/footable/footable.sortable.min.css' , false, '0.1.0' );
      wp_register_style( 'datatables', plugin_dir_url( __FILE__ ).'library/js/datatables/css/jquery.dataTables.min.css' , false, '1.10.0' );
      wp_register_style( 'datatables-responsive', plugin_dir_url( __FILE__ ).'library/js/datatables-responsive/files/1/css/datatables.responsive.css' , false, '1.10.0' );
      wp_register_style( $this->dash_prefix, plugin_dir_url( __FILE__ ).'library/css/wp-enqueuer-styles.css' , false, $this->version);
      wp_register_style( 'responsive-tabs', plugin_dir_url( __FILE__ ).'library/js/responsive-tabs/css/responsive-tabs.css' , false, '1.3.3' );
      // enqueue styles
      wp_enqueue_style( 'bootstrap-collapse' );
     // wp_enqueue_style( 'footable' );
     // wp_enqueue_style( 'footable-sortable' );
      wp_enqueue_style( 'datatables' );
      wp_enqueue_style( 'datatables-responsive' );
      wp_enqueue_style( 'responsive-tabs' );
      wp_enqueue_style( $this->dash_prefix );
    }

    /**
     * Load additional javascript in WordPress admin.
     *
     * Load datatables javascript in WordPress admin. One table for each registered post type.
     *
     * @return void
     */
    public function admin_enqueue_scripts_after(){
      if($_GET['page'] == 'wp-enqueuer-page.php'){
        //add responsive datatables to each post type
        $responsive_code = 'var breakpointDefinition = {
              tablet: 1024,
              phone : 480
          };';
        foreach($this->get_post_types() as $datatables ){
          $responsive_code .= 'var responsiveHelper_'.$datatables.' = undefined;
          var wp_enqueuer_'.$datatables.' = $(\'#wp_enqueuer_datatables_'.$datatables.'\');
          wp_enqueuer_'.$datatables.'.dataTable({
            "aaSorting": [
              [1,\'asc\']
            ],
            "sPaginationType": "full_numbers",
            //disable sorting on first column
            "aoColumnDefs" : [ 
              {
                \'bSortable\' : false,
                \'aTargets\' : [ 0 ]
              },
              {
                \'asSorting\': [ \'asc\' ],
                \'aTargets\': [ 1 ]
              }
            ],
            bAutoWidth: false,
            fnPreDrawCallback: function () {
                // Initialize the responsive datatables helper once.
                if (!responsiveHelper_'.$datatables.') {
                    responsiveHelper_'.$datatables.' = new ResponsiveDatatablesHelper(wp_enqueuer_'.$datatables.', breakpointDefinition);
                }
            },
            fnRowCallback  : function (nRow) {
                responsiveHelper_'.$datatables.'.createExpandIcon(nRow);
            },
            fnDrawCallback : function (oSettings) {
                responsiveHelper_'.$datatables.'.respond();
            },
          });
          //save the scripts the user enqueued when datatables pagination is enabled and the fields we selected are not on the current page
          $(document).on(\'click\',\'.wp_enqueuer_save\',function(){
            var data = wp_enqueuer_'.$datatables.'.$(\'input\').serializeArray();
            var append_fields;
            for( var i = 0; i < data.length; i++ ){
              if( typeof append_fields == \'undefined\' ){
                append_fields = \'<input type="hidden" name="\'+ data[i].name + \'" value="\'+ data[i].value+\'">\'; 
              }else{
                append_fields += \'<input type="hidden" name="\'+ data[i].name + \'" value="\'+ data[i].value+\'">\'; 
              }
            }
            $(\'#wp_enqueuer_settings_form\').append(append_fields);
          });
  ';
          
        }?>
        <script type='text/javascript'>
        /* <![CDATA[ */
        <!--START WP ENQUEUER JAVASCRIPT-->
          if (window.jQuery) {
            jQuery(document).ready(function($){
              <?php echo $responsive_code;?>
            });
          }
          <!--END WP ENQUEUER JAVASCRIPT
        /* ]]> */
        </script>
      <?php
      }
    }

    /**
     * Get the PHP code that's used to enqueue scripts.
     *
     * Allow users to manually add the enqueue code to the theme files or plugin files. Users can alter the code when manually adding enqueue code to their files.
     *
     * @param string $post_type Name of the post type.
     * @return string PHP code used to enqueue WordPress scripts.
     */
    public function admin_manual_enqueue($post_type){

      $enqueue_code = $this->front_enqueue_assets($post_type,true);
      if( !empty($enqueue_code) ){
        $manual_enqueue = "<pre class='".$this->prefix."manual_code'>\n&lt;?php\n".$enqueue_code."?&gt;</pre>";
      }

      return $manual_enqueue;
    }

    /**
     * Recursively search a multidimensional array for a value.
     *
     * Search for a value in one multidimensional array. This array can contain other multidimensional arrays.
     *
     * @see http://stackoverflow.com/questions/4128323/in-array-and-multidimensional-array
     * @param string|int $needle String or integer to look for.
     * @param array $haystack Multidimensional array to search in.
     * @param bool $strict Search for $needle by value and type.
     * @return bool True if value found or false otherwise.
     */
    public function in_array_r($needle, $haystack, $strict = false) {
      foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict))) {
            return true;
        }
      }

      return false;
    }

    /**
     * Recursively search a multidimensional array for a value and return it's position in the array.
     *
     * Search for a value in one multidimensional array. This array can contain other multidimensional arrays.
     *
     * @param string|int $needle String or integer to look for.
     * @param array $haystack Multidimensional array to search in.
     * @param bool $strict Search for $needle by value and type.
     * @param array $path The multidemsional array to search in.
     * @return array|bool Index of value in the multidimensional array or false if not found.
     */
    public function array_search_r( $needle, $haystack, $strict=false, $path=array() ){
      if( !is_array($haystack) ) {
          return false;
      }
   
      foreach( $haystack as $key => $val ) {
        if( is_array($val) && $subPath = $this->array_search_r($needle, $val, $strict, $path) ) {
            $path = array_merge($path, array($key), $subPath);
            return $path;
        } elseif( (!$strict && $val == $needle) || ($strict && $val === $needle) ) {
            $path[] = $key;
            return $path;
        }
      }
      return false;
    }

    /**
     * Select the script dependencies.
     *
     * If the user loads a script that depends on other scripts, the dependencies that should be loaded before the main script loads.
     *
     * @param string $dependencies Name of dependency to load.
     * @param string $type Type of dependency to load (stylesheet or javascript).
     * @param bool $footer Load the script in the footer.
     * @param bool $echo Execute the enqueue code or output the code content.
     * @return void
     */
    public function front_enqueue_deps($dependencies,$type,$footer = false,$echo = false){
      $assets = json_decode(json_encode($this->get_assets_file()),true);

      $deps = array();
      if( is_array($dependencies) ){
        foreach( $dependencies as $load_more_deps ){
          $this->front_enqueue_deps($load_more_deps,$type);
        }
      }else{
        $handle = $dependencies;
        $version = $assets[$type][$dependencies]['version'];
        $script_loader = array(
          'handle'=>$handle,
          'deps'=>$deps,
          'version'=>$version,
          );
        if( $assets[$type][$dependencies]['host'] === "local" )
          $source = plugin_dir_url( __FILE__ ).'assets/js/'.$assets[$type][$dependencies]['uri'];
        else
          $source = $assets[$type][$dependencies]['uri'];
        
        $script_loader['source'] = $source;
        if( $footer === true )
          $script_loader['footer'] = $footer;

        if( $echo === false )
          $this->front_enqueue_asset($type,$script_loader);
        elseif( $echo === true )
          return $this->front_enqueue_asset($type,$script_loader,$echo);

        // check to see if the script has any styles that come with it
        if( isset($assets[$type][$dependencies]['styles']) ){
          foreach($assets[$type][$dependencies]['styles'] as $script_style ){
            $script_style_loader = array(
              'handle'=>$handle,
              'deps'=>array(),
              'version'=>$version,
              'source'=>$script_style['uri']
              );

            if( $echo === false )
              $this->front_enqueue_asset('styles',$script_style_loader);
            elseif( $echo === true )
              return $this->front_enqueue_asset('styles',$script_style_loader);
          }
        }
      }

    }

    /**
     * Enqueue the javascript and css the user selected to load.
     *
     * @param string $type Type of dependency to load (stylesheet or javascript).
     * @param array $script_loader An array of values that are used as parameters to load scripts
     * @param bool $echo Execute the enqueue code or output the code content.
     * @return void
     */
    public function front_enqueue_asset($type,$script_loader = array(),$echo = false){
      if( $type === "scripts" ){
        extract($script_loader);
        // register script
        if( $echo === false ){
          wp_register_script( $handle, $source, $deps, $version, (isset($footer)?$footer:false) );
          // enqueue script
          wp_enqueue_script( $handle );
        }elseif( $echo === true ){
          $deps = implode("','",$deps);
          return "wp_register_script( '$handle', '$source', array('$deps'), '$version', ".(isset($footer)?$footer:"false")." );\nwp_enqueue_script( '$handle' );\n";
        }
        $this->loaded_scripts[] = $handle;
      }elseif( $type === "styles" ){
        extract($script_loader);
        // register style
        if( $echo === false ){
          wp_register_style( $handle, $source, $deps, $version, (isset($media)?$media:'') );
          // enqueue style
          wp_enqueue_style( $handle );
        }elseif( $echo === true ){
          $deps = implode("','",$deps);
          return "wp_register_style( '$handle', '$source', array('$deps'), '$version', '".(isset($media)?$media:'')."' );\nwp_enqueue_style( '$handle' );\n";
        }
        $this->loaded_styles[] = $handle;
      }
    }

    /**
     * Select the scripts the user wants to load.
     *
     * Select the scripts from the database the user selected to load. Select scripts based on the current post type.
     *
     * @param string $current_post_type The name of the post type to enqueue scripts for.
     * @param bool $echo Execute the enqueue code or output the code content.
     * @return void
     */
    public function front_enqueue_assets($current_post_type = "",$echo = false){
      $post_types = $this->get_post_types();

      $script_holder = "";

      if( !empty($post_types) ){
        // get the current post
        if( empty($current_post_type) )
          $current_post_type = get_post_type();

        $scripts = $this->get_enqueued_scripts($current_post_type);
        if( !empty($scripts) ){

          // load the script loader file
          $assets = json_decode(json_encode($this->get_assets_file()),true);
          if( !empty($scripts[$this->prefix.$current_post_type]) ){
            foreach ( $scripts[$this->prefix.$current_post_type] as $script_name => $script) {
              $script_deps = array();
              // loop through and grab dependencies
              if( is_array($assets[$script['type']][$script_name]['deps'])){
                foreach( $assets[$script['type']][$script_name]['deps'] as $dep ){
                  $script_deps[] = $dep['name'];
                }
              }

              // check to see if we want to load the dependencies
              if( $script['deps'] === false AND !empty($script_deps) ){
                // check if the main script is loading in the footer
                if( isset($script['footer']) )
                  $footer = true;
                // loop through dependencies to load them
                foreach( $script_deps as $dep ){
                  /* check to see if the dependencies have been loaded already AND if the dependent script is not a main script (prevents the dependent script from loading in the footer [if the user selected the main script to load in the footer], if the user selected this same script to load in the header)*/
                  if( (!in_array($dep,$this->loaded_scripts) OR !in_array($dep,$this->loaded_styles)) AND !array_key_exists($dep, $scripts[$this->prefix.$current_post_type]) ){
                    $script_holder .= $this->front_enqueue_deps($dep,$script['type'],$footer,$echo);
                  }
                }
              }

              // load the main script
              $handle = $script_name;
              $script_loader = array(
                'handle'=>$handle,
                'deps'=>$script_deps,
                'version'=>$assets[$script['type']][$script_name]['version']
                );

              if( $assets[$script['type']][$script_name]['host'] === "local" )
                $source = plugin_dir_url( __FILE__ ).'assets/js/'.$assets[$script['type']][$script_name]['uri'];
              else
                $source = $assets[$script['type']][$script_name]['uri'];

              $script_loader['source'] = $source;

              if( isset($script['footer']) )
                $script_loader['footer'] = $script['footer'];
              $script_holder .= $this->front_enqueue_asset($script['type'],$script_loader,$echo);

              // check to see if the script has any styles that come with it
              if( isset($assets[$script['type']][$script_name]['styles']) ){
                foreach($assets[$script['type']][$script_name]['styles'] as $script_style ){
                  $handle = $script_name;

                  $style_loader = array(
                    'handle'=>$handle,
                    'deps'=>array(),
                    'version'=>$script_loader['version'],
                    'source'=>$script_style['uri']
                    );
                  $script_holder .= $this->front_enqueue_asset('styles',$style_loader,$echo);
                }
              }

            }
          }
        }
      }

      return $script_holder;
    }
  }
   
  // Store a reference to the plugin in GLOBALS so that our unit tests can access it
  $GLOBALS['wp-enqueuer'] = new WP_Enqueuer();
     
}
