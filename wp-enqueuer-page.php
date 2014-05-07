<?php
$wp_enqueuer = $GLOBALS['wp-enqueuer'];
wp_create_nonce( 'wp_enqueuer_nonce' );
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
            <input type="text" class="wp_enqueuer_footable_filter" id="wp_enqueuer_footable_filter_<?php _e( $post_type );?>">
            <table class="wp_enqueuer_post_types widefat" data-toggle="checkboxes" data-range="true" data-page-size="10" data-page-previous-text="prev" data-page-next-text="next" data-page-navigation=".pagination" data-filter="#wp_enqueuer_footable_filter_<?php _e( $post_type );?>">
              <thead>
                <tr>
                  <th data-sort-ignore="true"><?php _e( 'Select' );?></th>
                  <th data-sort-initial="true"><?php _e( 'Name' );?></th>
                  <th><?php _e( 'Version' );?></th>
                  <th><?php _e( 'Type' );?></th>
                  <th data-toggle="true"><?php _e( 'Location' );?></th>
                </tr>
              </thead>
              <?php
              $assets = $wp_enqueuer->get_assets_file();
              if( !empty($assets) ):?>
              <tbody>
                <?php foreach( $assets->scripts as $script ):?>
                <tr>
                  <td><input type="checkbox" name="wp_enqueuer_<?php _e( $post_type );?>[]" value="<?php _e( $script->name );?>" class="wp_enqueuer"></td>
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
              <tfoot>
                <tr>
                  <td colspan="5">
                  <div class="pagination pagination-centered hide-if-no-paging"></div>
                  </td>
                </tr>
              </tfoot>
              <?php endif;?>
            </table>
          </div>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <input name="wp_enqueuer_save" class="button button-primary button-large" id="publish" accesskey="p" value="Save Settings" type="submit">
  </form>
  <?php endif;?>
</div>