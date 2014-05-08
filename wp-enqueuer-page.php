<?php
$wp_enqueuer = $GLOBALS['wp-enqueuer'];
$scripts = $wp_enqueuer->get_enqueue_unique();
?>
<div class="wrap">
  <h2><?php _e( 'WP Enqueuer Settings' );?></h2>
  <?php 
  $public_post_types = $wp_enqueuer->get_post_types();
  if( !empty( $public_post_types ) ): $count = 0;?>
  <form method="post" action="options-general.php?page=wp-enqueuer-page.php">
    <input name="wp_enqueuer_save" class="button button-primary button-large" id="publish" accesskey="p" value="Save Settings" type="submit">
    <div class="panel-group" id="wp_enqueuer_post_types">
      <?php foreach( $public_post_types as $post_type ):?>
      <div class="panel panel-default">
        <div class="panel-heading">
          <h4 class="panel-title">
            <a class="collapsed" data-toggle="collapse" data-parent="#wp_enqueuer_post_types" href="#wp_enqueuer_<?php echo $count;?>"><?php echo $post_type;?></a>
          </h4>
        </div>
        <div id="wp_enqueuer_<?php echo $count;?>" class="panel-collapse collapse in">
          <div class="panel-body">
            <table class="wp_enqueuer_post_types widefat" id="wp_enqueuer_<?php _e( $post_type );?>" data-toggle="checkboxes" data-range="true">
              <thead>
                <tr>
                  <th data-sort-ignore="true"><?php _e( 'Select' );?></th>
                  <th data-sort-initial="true"><?php _e( 'Name' );?></th>
                  <th data-hide="phone,tablet"><?php _e( 'Version' );?></th>
                  <th><?php _e( 'Type' );?></th>
                  <th data-class="expand"><?php _e( 'Location' );?></th>
                </tr>
              </thead>
              <?php
              $assets = $wp_enqueuer->get_assets_file();
              if( !empty($assets) ):?>
              <tbody>
                <?php foreach( $assets->scripts as $script ):?>
                <tr>
                  <td><input type="checkbox" name="wp_enqueuer_<?php _e( $post_type );?>[]" value="<?php _e( $script->name );?>" 
                  <?php
                  //check to see if the script was selected or is dependent on another script
                  if( !empty($scripts['wp_enqueuer_'.$post_type]) ){

                      foreach( $scripts['wp_enqueuer_'.$post_type] as $check_script ){

                        if( !is_array($check_script) ){
                          if( $check_script === $script->name )
                            echo "checked";

                        }
                      }
                    }
                    ?> class="wp_enqueuer"></td>
                  <td><?php _e( $script->name );?></td>
                  <td><?php _e( $script->version );?></td>
                  <td><?php _e( 'Javascript' );?></td>
                  <td><?php _e( $script->host );?></td>
                </tr>
                <?php endforeach;?>
                <?php foreach( $assets->styles as $script ):?>
                <tr>
                  <td><input type="checkbox" name="wp_enqueuer_<?php _e( $post_type );?>[]" value="<?php _e( $script->name );?>"></td>
                  <td><?php _e( $script->name );?></td>
                  <td><?php _e( $script->version );?></td>
                  <td><?php _e( 'CSS' );?></td>
                  <td><?php _e( $script->host );?></td>
                </tr>
                <?php endforeach;?>
              </tbody>
              <?php endif;?>
            </table>
          </div>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <?php wp_nonce_field( 'wp_enqueuer_save_settings', 'wp_enqueuer_settings' );?>
    <input name="wp_enqueuer_save" class="button button-primary button-large" id="publish" accesskey="p" value="Save Settings" type="submit">
  </form>
  <?php endif;?>
</div>