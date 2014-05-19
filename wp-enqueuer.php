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
 
  class WP_Enqueuer {
    
    /**
     * File name that stores available scripts
     *
     * @var string
     */
    private $script_file;

    /**
     * Store version of the plugin
     *
     * @var string
     */
    private $version;
    private $loaded_scripts;
    private $loaded_styles;
    private $dep_scripts;

    /**
     * Prefix for all keys in the plugin
     *
     * @var string
     */
    private $prefix;

    public function __construct() {
      $this->script_file = 'wp-enqueuer-scripts.json';
      $this->version = '1.0a';
      $this->prefix = 'wp_enqueuer_';
      //hold list of enqueued scripts so we don't enqueue them twice
      $this->loaded_scripts = array();
      $this->loaded_styles = array();
      $this->dep_scripts = array();
      //load WordPress hooks
      $this->admin_hooks();
      $this->front_hooks();
    }

    public function admin_hooks(){
      add_action( 'admin_menu', array(&$this,'settings_page') );
      add_action( 'admin_enqueue_scripts', array(&$this,'admin_enqueue') );
      add_action( 'admin_init', array( $this, 'set_enqueue' ) );
      add_action( 'admin_head', array( $this, 'admin_enqueue_scripts_after' ) );  
    }

    public function front_hooks(){
      add_action( 'wp_enqueue_scripts', array(&$this,'front_enqueue_assets') );
    }

    public function install(){

    }


    public function get_assets_file(){
      $assets_file = file_get_contents($this->get_assets_file_path());

      if( $assets_file != false )
        $assets_file = json_decode($assets_file);
      else
        $assets_file = false;

      return $assets_file;
    }

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

    public function get_post_types_name($post_type = ''){
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

    public function set_enqueue_deps($asset_enqueue,$script_name){
      $dependencies = array();
      foreach( $asset_enqueue as $dep ){
        $dependencies[] = $dep['name'];
      }
      return $dependencies;
    }

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
                  $asset_details = implode('',$asset_details);
                  $script_name = $type[0];
                  $this->loaded_scripts[$script_name] = array(
                      'footer'=>(!empty($load_in_footer[$script_name])?false:true),
                      'deps'=>(!empty($load_deps[$script_name])?true:false),
                      'type'=>$type
                    );
                }
                
              }

              $result = update_option( $this->prefix.$key, (!empty($this->loaded_scripts)?$this->loaded_scripts:null) );
            }
          }
          return $this->loaded_scripts;
        }
      }
    }

    public function get_enqueue($post_type = null){
      $post_types = $this->get_post_types();

      $scripts = false;
      if( !empty($post_types) AND is_null($post_type) ){
        $scripts = array();
        foreach( $post_types as $key=>$value ){
          if ( get_option( $this->prefix.$key ) !== false ) {
            $scripts[$this->prefix.$key] = get_option( $this->prefix.$key );
          }
        }
      }elseif( !is_null($post_type) ){
        if ( get_option( $this->prefix.$post_type ) !== false ) {
          $scripts[$this->prefix.$post_type] = get_option( $this->prefix.$post_type );
        }
      }

      return $scripts;
    }

    /**
     * Get the scripts the user wanted to load
     *
     * Loop through list of scripts the user selected to download, return multidimensional associative array
     *
     * @return array|false multidimensional associative array or false if no scripts selected
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
     * Get the names of scripts that load in the footer
     *
     * If the user selected the script to load in the footer, get the script name
     *
     * @return array|bool multidimensional associative array or false if no scripts are loading in the footer
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

    public function settings_page(){
      add_options_page( 'WP Enqueuer Settings', 'WP Enqueuer', 'manage_options', 'wp-enqueuer-page.php', array(&$this,'load_settings_page') );
    }

    public function load_settings_page(){
      include( 'wp-enqueuer-page.php' );
    }

    public function admin_enqueue($hook){
      if( 'settings_page_wp-enqueuer-page' === $hook ){
        $this->admin_enqueue_scripts();
        $this->admin_enqueue_styles();
      }
    }

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

    public function admin_enqueue_styles(){
      // register styles
      wp_register_style( 'bootstrap-collapse', plugin_dir_url( __FILE__ ).'library/js/bootstrap/css/bootstrap.css' , false, '3.1.1' );
      //wp_register_style( 'footable', plugin_dir_url( __FILE__ ).'library/js/footable/footable.min.css' , false, '0.1.0' );
      //wp_register_style( 'footable-sortable', plugin_dir_url( __FILE__ ).'library/js/footable/footable.sortable.min.css' , false, '0.1.0' );
      wp_register_style( 'datatables', plugin_dir_url( __FILE__ ).'library/js/datatables/css/jquery.dataTables.min.css' , false, '1.10.0' );
      wp_register_style( 'datatables-responsive', plugin_dir_url( __FILE__ ).'library/js/datatables-responsive/files/1/css/datatables.responsive.css' , false, '1.10.0' );
      wp_register_style( 'wp-enqueuer', plugin_dir_url( __FILE__ ).'library/css/wp-enqueuer-styles.css' , false, $this->version);
      wp_register_style( 'responsive-tabs', plugin_dir_url( __FILE__ ).'library/js/responsive-tabs/css/responsive-tabs.css' , false, '1.3.3' );
      // enqueue styles
      wp_enqueue_style( 'bootstrap-collapse' );
     // wp_enqueue_style( 'footable' );
     // wp_enqueue_style( 'footable-sortable' );
      wp_enqueue_style( 'datatables' );
      wp_enqueue_style( 'datatables-responsive' );
      wp_enqueue_style( 'responsive-tabs' );
      wp_enqueue_style( 'wp-enqueuer' );
    }

    public function admin_enqueue_scripts_after(){
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

    public function in_array_r($needle, $haystack, $strict = false) {
      foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict))) {
            return true;
        }
      }

      return false;
    }

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

    public function front_enqueue_deps($dependencies,$type){
      $assets = json_decode(json_encode($this->get_assets_file()),true);

      foreach( $assets[$type] as $key=>$asset ){
        $deps = array();
        if( is_array($dependencies) ){
          foreach( $dependencies as $load_more_deps ){
            $this->front_enqueue_deps($load_more_deps,$type);
          }
          break;
        }else{

          $handle = $asset[$key];
          $version = $asset['version'];

          if( $type === 'scripts' ){
            $script_loader = array(
              'handle'=>$handle,
              'deps'=>$deps,
              'version'=>$version,
              );
            if( $asset['host'] === "local" )
              $source = plugin_dir_url( __FILE__ ).'assets/js/'.$asset['uri'];

            // check to see if the script has any styles that come with it
            if( isset($asset['styles']) ){
              foreach($asset['styles'] as $script_style ){
                $script_style_loader = array(
                  'handle'=>$handle,
                  'deps'=>array(),
                  'version'=>$version,
                  'source'=>$script_style['uri']
                  );
                $this->front_enqueue_asset('styles',$script_style_loader);
              }
            }
          }elseif( $type === 'styles' ){
            $script_loader = array(
              'handle'=>$handle,
              'deps'=>$deps,
              'version'=>$version,
              );

            if( $asset['host'] === "local" )
              $source = plugin_dir_url( __FILE__ ).'assets/css/'.$asset['uri'];

          }

          if( !isset($source) )
            $source = $asset['uri'];

          $script_loader['source'] = $source;

          $this->front_enqueue_asset($type,$script_loader);
          break;
        }

      }

    }

    public function front_enqueue_asset($type,$script_loader = array()){
      if( $type === "scripts" ){
        extract($script_loader);
        // register script
        wp_register_script( $handle, $source, $deps, $version, (isset($footer)?$footer:false) );
        // enqueue script
        wp_enqueue_script( $handle );
        $this->loaded_scripts[] = $handle;
      }elseif( $type === "styles" ){
        extract($script_loader);
        // register style
        wp_register_style( $handle, $source, $deps, $version, (isset($media)?$media:'') );
        // enqueue style
        wp_enqueue_style( $handle );
        $this->loaded_styles[] = $handle;
      }
    }

    public function front_enqueue_assets(){
      $post_types = $this->get_post_types();

      if( !empty($post_types) ){
        $current_post_type = get_post_type();
        $scripts = $this->get_enqueue($current_post_type);

        if( !empty($scripts) ){

          // load the script loader file
          $assets = json_decode(json_encode($this->get_assets_file()),true);

          foreach ( $scripts[$this->prefix.$current_post_type] as $key => $script) {
            
            $script_deps = array();
            // check to see if we want to load the dependencies
            if( $script['deps'] != false ){
              foreach( $script as $dep ){
                // check to see if the dependencies have been loaded already
                if( !in_array($dep,$this->loaded_scripts) OR !in_array($dep,$this->loaded_styles) ){
                  $script_deps = $this->front_enqueue_deps($dep,'scripts');
                  $script_deps = $this->front_enqueue_deps($dep,'styles');
                }
              }
            }

            // load the main script
            $handle = $assets['scripts'][$script];
            $script_loader = array(
              'handle'=>$handle,
              'deps'=>$script_deps,
              'version'=>$assets['scripts'][$script]['version'],
              );

            if( $assets['scripts'][$script]['host'] === "local" )
              $source = plugin_dir_url( __FILE__ ).'assets/js/'.$assets['scripts'][$script]['uri'];
            else
              $source = $assets['scripts'][$script]['uri'];

            $script_loader['source'] = $source;
            $this->front_enqueue_asset('scripts',$script_loader);

            // check to see if the script has any styles that come with it
            if( isset($assets['scripts'][$script]['styles']) ){
              foreach($assets['scripts'][$script]['styles'] as $script_style ){
                $handle = $assets['scripts'][$script];

                $style_loader = array(
                  'handle'=>$handle,
                  'deps'=>array(),
                  'version'=>$assets['scripts'][$script]['version'],
                  'source'=>$script_style['uri']
                  );
                $this->front_enqueue_asset('styles',$style_loader);
              }
            }

          }
        }
      }
    }
  }
   
  // Store a reference to the plugin in GLOBALS so that our unit tests can access it
  $GLOBALS['wp-enqueuer'] = new WP_Enqueuer();
     
}
