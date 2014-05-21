<?php
$wp_enqueuer = $GLOBALS['wp-enqueuer'];
$scripts = $wp_enqueuer->get_enqueue();
?>
<div class="wrap" id="wp_enqueuer_settings_page">
  <h2><?php _e( 'WP Enqueuer Settings' );?></h2>

  <?php 
  $public_post_types = $wp_enqueuer->get_post_types();
  if( !empty( $public_post_types ) ): $count = 0;?>
  <form method="post" action="options-general.php?page=wp-enqueuer-page.php" id="wp_enqueuer_settings_form">
    <p><?php _e( 'Select the scripts and styles you want to load' );?></p>
    <p><?php _e( 'How to use:' );?>
    <ul>
      <li><?php _e( 'Dependencies are automatically enqueued' );?></li>
      <li><?php _e( 'Select multiple items to enqueue by clicking a checkbox and holding down the <strong>"Shift"</strong> key and clicking other items between' );?></li>
      </ul> 
    <input name="wp_enqueuer_save" class="button button-primary button-large wp_enqueuer_save" accesskey="p" value="Save Settings" type="submit">
    <div id="wp_enqueuer_tabs">
      <!--TAB TITLE-->
      <ul>
      <?php foreach( $public_post_types as $post_type ):?>
        <li>
          <h2 class="nav-tab-wrapper">
          <a href="#wp_enqueuer_<?php _e( $post_type );?>">
            <?php _e( $wp_enqueuer->get_post_types_name($post_type) );?>
          </a>
          </h2>
        </li>
      <?php endforeach;?>
      </ul>
      
      <!--TAB BODY-->
      <?php foreach( $public_post_types as $post_type ):?>
        <div id="wp_enqueuer_<?php echo $post_type;?>">
          <table class="wp_enqueuer_post_types widefat" data-toggle="checkboxes" data-range="true" id="wp_enqueuer_datatables_<?php _e( $post_type );?>">
            <thead>
              <tr>
                <th data-sort-ignore="true"><?php _e( 'Select' );?></th>
                <th data-sort-initial="true"><?php _e( 'Name' );?></th>
                <th data-hide="phone,tablet"><?php _e( 'Version' );?></th>
                <th data-class="expand"><?php _e( 'Type' );?></th>
                <th><?php _e( 'Host' );?></th>
                <th data-hide="phone,tablet"><?php _e( 'Deps' );?></th>
                <th data-hide="phone,tablet"><?php _e( 'Dont\'t Load Deps' );?></th>
                <th data-sort-ignore="true"><?php _e( 'Footer' );?></th>
              </tr>
            </thead>
            <?php
            $assets = $wp_enqueuer->get_assets_file();
            if( !empty($assets) ):?>
            <tbody>
              <?php foreach( $assets->scripts as $key=>$script ):?>
              <tr>
                <td><input type="checkbox" name="wp_enqueuer_<?php _e( $post_type );?>[]" value="<?php _e( $key.'_scripts' );?>" 
                  <?php
                  //check to see if the script was selected or is dependent on another script
                  if( !empty($scripts['wp_enqueuer_'.$post_type]) ){

                    if( array_key_exists($key, $scripts['wp_enqueuer_'.$post_type]) ){
                        echo "checked";
                      }
                  }
                  ?> class="wp_enqueuer"></td>
                <td><?php _e( $key );?></td>
                <td><?php _e( $script->version );?></td>
                <td><?php _e( 'Javascript' );?></td>
                <td><?php _e( $script->host );?></td>
                <td>
                  <?php
                  $dep = "None";
                  if( isset($script->deps) ){
                    echo "<ul>";
                    foreach($script->deps as $dep){
                      _e( "<li>".$dep->name."</li>" );
                    }
                    echo "</ul>";
                  }else{
                    _e( $dep );
                  }
                  ?>
                </td>
                <td><input type="checkbox" name="wp_enqueuer_<?php _e( $post_type );?>_deps[]" value="<?php _e( $key );?>" 
                  <?php
                  //check to see if the script was selected 
                  if( !empty($scripts['wp_enqueuer_'.$post_type.'_deps']) ){

                    if( array_key_exists($key, $scripts['wp_enqueuer_'.$post_type.'_deps']) ){
                        echo "checked";
                      }
                  }
                  ?> class="wp_enqueuer"></td>
                <td><input type="checkbox" name="wp_enqueuer_<?php _e( $post_type );?>_footer[]" value="<?php _e( $key );?>" 
                  <?php
                  //check to see if the script was selected or is dependent on another script
                  if( !empty($scripts['wp_enqueuer_'.$post_type.'_footer']) ){

                    if( array_key_exists($key, $scripts['wp_enqueuer_'.$post_type.'_footer']) ){
                        echo "checked";
                      }
                  }
                  ?> class="wp_enqueuer"></td>
              </tr>
              <?php endforeach;?>
              <?php foreach( $assets->styles as $key=>$script ):?>
              <tr>
                <td><input type="checkbox" name="wp_enqueuer_<?php _e( $post_type );?>[]" value="<?php _e( $key.'_styles' );?>" 
                <?php
                  //check to see if the script was selected
                  if( !empty($scripts['wp_enqueuer_'.$post_type]) ){

                    if( array_key_exists($key, $scripts['wp_enqueuer_'.$post_type]) ){
                        echo "checked";
                      }
                  }
                  ?>></td>
                <td><?php _e( $key );?></td>
                <td><?php _e( $script->version );?></td>
                <td><?php _e( 'CSS' );?></td>
                <td><?php _e( $script->host );?></td>
                <td>
                  <?php
                  $dep = "None";
                  if( isset($script->deps) ){
                    echo "<ul>";
                    foreach($script->deps as $dep){
                      _e( "<li>".$dep->name."</li>" );
                    }
                    echo "</ul>";
                  }else{
                    _e( $dep );
                  }
                  ?>
                </td>
                <td><input type="checkbox" name="wp_enqueuer_<?php _e( $post_type );?>_deps[]" value="<?php _e( $key );?>" 
                  <?php
                  //check to see if the script was selected 
                  if( !empty($scripts['wp_enqueuer_'.$post_type.'_deps']) ){

                    if( array_key_exists($key, $scripts['wp_enqueuer_'.$post_type.'_deps']) ){
                        echo "checked";
                      }
                  }
                  ?> class="wp_enqueuer"></td></td>
                <td></td>
              </tr>
              <?php endforeach;?>
            </tbody>
            <?php endif;?>
          </table>
        </div>
      <?php endforeach;?>
    </div>
    <?php wp_nonce_field( 'wp_enqueuer_save_settings', 'wp_enqueuer_settings' );?>
    <input name="wp_enqueuer_save" class="button button-primary button-large wp_enqueuer_save" accesskey="p" value="Save Settings" type="submit">
  </form>
  <?php endif;?>
</div>