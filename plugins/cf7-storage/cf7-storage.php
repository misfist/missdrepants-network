<?php
/*
	Plugin Name: Contact Form 7 Storage
	Description: Store all Contact Form 7 submissions (including attachments) in your WordPress dashboard.
	Plugin URI: http://preseto.com/plugins/contact-form-7-storage
	Author: Kaspars Dambis
	Author URI: http://kaspars.net
	Version: 1.0.3
	License: GPL2
	Text Domain: cf7-storage
*/


cf7_storage::instance();


class cf7_storage {

	public static $instance;
	private static $post_type = 'cf7_entry';
	private $admin_action = 'index';


	public static function instance() {

		if ( ! self::$instance )
			self::$instance = new self();

		return self::$instance;

	}


	private function __construct() {

		add_action( 'init', array( $this, 'init_l10n' ) );

		// Define storage post type
		add_action( 'init', array( $this, 'storage_init' ) );

		// Capture and store form submission
		add_action( 'wpcf7_mail_sent', array( $this, 'storage_capture' ) );

		// Add admin view
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

	}


	function init_l10n() {

		load_plugin_textdomain( 'cf7-storage', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	}


	function storage_init() {

		register_post_type( 
			self::$post_type, 
			array(
				'public' => false,
				'label' => __( 'Entries', 'cf7-storage' ),
				'supports' => false,
			)
		);

	}


	function storage_capture( $cf7 ) {

		$mail = $cf7->compose_mail( 
				$cf7->setup_mail_template( $cf7->mail, 'mail' ),
				false // Don't send
			);

		$entry_id = wp_insert_post( array(
				'post_title' => $mail['sender'],
				'post_type' => self::$post_type, 
				'post_status' => 'publish',
				'post_parent' => $cf7->id,
				'post_content' => $mail['body']
			) );

		foreach ( $mail as $mail_field => $mail_value )
			add_post_meta( $entry_id, 'mail_' . $mail_field, $mail_value );

		// Store all the meta data
		foreach ( $cf7->posted_data as $key => $value )
			add_post_meta( $entry_id, 'cf7_' . $key, $value );

		// Store user browser, IP, referer
		$extra_meta = array(
			'http_user_agent' => $_SERVER['HTTP_USER_AGENT'],
			'remote_addr' => $_SERVER['REMOTE_ADDR'],
			'http_referer' => $_SERVER['HTTP_REFERER']
		);

		foreach ( $extra_meta as $key => $value )
			add_post_meta( $entry_id, $key, $value );

		// Store uploads permanently
		$uploads_dir = wp_upload_dir();
		$storage_dir = sprintf( '%s/cf7-storage', $uploads_dir['basedir'] );

		if ( ! is_dir( $storage_dir ) )
			mkdir( $storage_dir );

		foreach ( $cf7->uploaded_files as $name => $path ) {

			if ( ! isset( $_FILES[ $name ] ) )
				continue;

			$extension = pathinfo( $path, PATHINFO_EXTENSION );

			$destination = sprintf( 
					'%s/%d-%s.%s', 
					$storage_dir,
					$entry_id,
					md5( $path ), 
					$extension 
				);

			// Copy to a permanant storage location
			@copy( 
				$path, 
				$destination
			);

		}

		do_action( 'cf7_storage_capture', $entry_id, $cf7 );

	}


	function admin_menu() {

		// Register a subpage for Contact Form 7
		$cf7_subpage = add_submenu_page( 
				'wpcf7',
				__( 'Contact Form Entries', 'cf7-storage' ),
				__( 'Entries', 'cf7-storage' ),
				'wpcf7_read_contact_forms', 
				'cf7_storage',
				array( $this, 'admin_page' )
			);

		add_action( 'load-' . $cf7_subpage, array( $this, 'admin_actions_process' ) );

	}


	function admin_actions_process() {

		if ( ! isset( $_REQUEST['action'] ) || empty( $_REQUEST['action'] ) )
			return;

		$action = $_REQUEST['action'];

		// This is a non-action request such as sort or filter by contact form, date, etc.
		// so we redirect back to the referer
		if ( empty( $action ) && isset( $_REQUEST[ '_wp_http_referer' ] ) ) {

			wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_get_referer() ) );
			exit;

		}

		$action_whitelist = array( 
				'trash', 
				'delete', 
				'untrash', 
				'delete_all'
			);

		if ( ! in_array( $action, $action_whitelist ) )
			return;

		check_admin_referer( 'bulk-posts' );

		$sendback = remove_query_arg( 
				array( 
					'trashed', 
					'untrashed', 
					'deleted', 
					'locked', 
					'ids',
					'action', 
					'action2',
					'post_id', 
					'ids',
					'post_status',
					'_wp_http_referer',
					'_wpnonce'
				), 
				wp_get_referer()
			);

		$post_ids = array();

		// Collect the post IDs we need to act on
		if ( 'delete_all' == $action && isset( $_REQUEST['post_status'] ) ) {

			// Get all posts in trash
			$post_ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_status = %s", 
					self::$post_type, 
					'trash' 
				) );

			$action = 'delete';

		} elseif ( isset( $_REQUEST['ids'] ) && ! empty( $_REQUEST['ids'] ) ) {

			$post_ids = explode( ',', $_REQUEST['ids'] );

		} elseif ( isset( $_REQUEST['post_id'] ) && ! empty( $_REQUEST['post_id'] ) ) {

			$post_ids = array( (int) $_REQUEST['post_id'] );

		} elseif ( isset( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) ) {
			
			$post_ids = array_map( 'intval', $_REQUEST['post'] );

		}

		// Nothing to edit, trash or delete, redirect back
		if ( empty( $post_ids ) ) {
			
			wp_redirect( $sendback );
			exit();

		}

		switch ( $action ) {

			case 'trash' :

				foreach( $post_ids as $post_id ) {

					if ( ! current_user_can( 'delete_post', $post_id ) )
						wp_die( __( 'You are not allowed to move this item to Trash.', 'cf7-storage' ) );

					if ( ! wp_trash_post( $post_id ) )
						wp_die( __( 'Error moving an item to Trash.', 'cf7-storage' ) );

					$sendback = add_query_arg( array(
								'trashed' => true 
							), 
							$sendback 
						);
				}

				break;

			case 'untrash':

				foreach ( $post_ids as $post_id ) {

					if ( ! current_user_can( 'delete_post', $post_id ) )
						wp_die( __( 'You are not allowed to restore this item from Trash.', 'cf7-storage' ) );

					if ( ! wp_untrash_post( $post_id ) )
						wp_die( __( 'Error in restoring an item from Trash.', 'cf7-storage' ) );

				}

				$sendback = add_query_arg(
						'untrashed', 
						true, 
						$sendback
					);

				break;

			case 'delete' :

				foreach( $post_ids as $post_id ) {

					if ( ! current_user_can( 'delete_post', $post_id ) )
						wp_die( __( 'You are not allowed to move this item to Trash.', 'cf7-storage' ) );

					if ( ! wp_delete_post( $post_id, true ) )
						wp_die( __( 'Error in deleting an item.', 'cf7-storage' ) );

					$sendback = add_query_arg( array(
								'deleted' => true 
							), 
							$sendback 
						);
				}

				break;

		}

		wp_redirect( $sendback );
		exit();

	}


	function admin_page() {

		$action = 'index';

		if ( isset( $_REQUEST['action'] ) )
			$action = $_REQUEST['action'];

		switch ( $action ) {

			case 'view' :

				if ( ! isset( $_REQUEST['post_id'] ) )
					wp_die( __( 'Missing entry ID.', 'cf7-storage' ) );
				
				$post_id = $_REQUEST['post_id'];

				// We are viewing this entry now
				$this->admin_single_entry( $post_id );

				break;

			default :

				// Include our list view
				include 'inc/admin-list-view.php';

				// List of all entries
				$this->admin_entry_index();

				break;

		}

	}

	function admin_entry_index() {
		
		$list_table = new cf7_storage_list_table( self::$post_type );
		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h2>
			<?php
				esc_html_e( 'Contact Form Entries', 'cf7-storage' );

				if ( ! empty( $_REQUEST['s'] ) ) {

					printf( 
						'<span class="subtitle">%s</span>',
						esc_html( sprintf( 
							__( 'Search results for "%s"', 'cf7-storage' ),
							$_REQUEST['s']
						) )
					);

				}
			?>
			</h2>

			<?php do_action( 'cf7_storage_admin_notices' ); ?>

			<?php $list_table->views(); ?>

			<form method="get" action="">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
				<?php 
					$list_table->search_box( __( 'Search Entries', 'cf7-storage' ), self::$post_type ); 
					$list_table->display(); 
				?>
			</form>

		</div>
		<?php

	}


	function admin_single_entry( $post_id ) {

		$post = get_post( (int) $post_id );

		if ( empty( $post ) )
			wp_die( __( 'This contact form submission doesn\'t exist!', 'cf7-storage' ) );

		if ( $post->post_type !== self::$post_type )
			return;

		// Enqueue our admin style
		wp_enqueue_style( 'cf7s-style', plugins_url( 'assets/css/cf7s-admin.css', __FILE__ ) );

		// Prepare links to attachments
		$attachments = get_post_meta( $post->ID, 'mail_attachments', true );

		if ( ! empty( $attachments ) ) {

			$uploads_dir = wp_upload_dir();
			$storage_url = sprintf( '%s/cf7-storage', $uploads_dir['baseurl'] );
			$attachment_list = array();

			foreach ( $attachments as $url ) {

				$extension = pathinfo( $url, PATHINFO_EXTENSION );
			
				$attachment_list[] = sprintf( 
					'<li><a href="%s">%s</a></li>',
					esc_url( sprintf( '%s/%d-%s.%s', $storage_url, $post_id, md5( $url ), $extension ) ),
					esc_html( basename( $url ) )
				);

			}

			$maybe_attachments = sprintf(
					'<ul>%s<ul>',
					implode( '', $attachment_list )
				);

		} else {
			
			$maybe_attachments = _x( 'None', 'No attachments found', 'cf7-storage' );

		}

		$timestamp = strtotime( $post->post_date );

		$rows = array(
			'form-link' => array(
				'label' => __( 'Form:', 'cf7-storage' ),
				'value' => sprintf(
	                		'<a href="%s">%s</a>',
	                		admin_url( sprintf( 'admin.php?page=wpcf7&post=%d&action=edit', $post->post_parent ) ),
	                		esc_html( get_the_title( $post->post_parent ) )
	        		)
			),
			'from' => array(
				'label' => __( 'From:', 'cf7-storage' ),
				'value' => esc_html( get_post_meta( $post->ID, 'mail_sender', true ) )
			),
			'to' => array(
				'label' => __( 'To:', 'cf7-storage' ),
				'value' => esc_html( get_post_meta( $post->ID, 'mail_recipient', true ) )
			),
			'date' => array(
				'label' => __( 'Date:', 'cf7-storage' ),
				'value' => esc_html( sprintf(
					'%s %s',
					date_i18n( get_option( 'date_format' ), $timestamp ),
					date_i18n( get_option( 'time_format' ), $timestamp )
				) )
			),
			'subject' => array(
				'label' => __( 'Subject:', 'cf7-storage' ),
				'value' => esc_html( get_post_meta( $post->ID, 'mail_subject', true ) )
			),
			'body' => array(
				'label' => __( 'Message:', 'cf7-storage' ),
				'value' => sprintf(
					'<div class="body-content-wrap">%s</div>',
					apply_filters( 'the_content', $post->post_content )
				)
			),
			'attachments' => array(
				'label' => __( 'Attachments:', 'cf7-storage' ),
				'value' => $maybe_attachments
			),
			'referer' => array(
				'label' => __( 'Referer:', 'cf7-storage' ),
				'value' => esc_html( get_post_meta( $post->ID, 'http_referer', true ) )
			),
			'user-agent' => array(
				'label' => __( 'User agent:', 'cf7-storage' ),
				'value' => esc_html( get_post_meta( $post->ID, 'http_user_agent', true ) )
			)
		);

		// Allow other plugins to add more elements to our message view
		$rows = apply_filters( 'cf7_entry_rows', $rows, $post );

		$rows_html = array();

		foreach ( $rows as $row_id => $row_elements ) {

			$rows_html[] = sprintf(
				'<tr class="cf7s-%s">
					<th>%s</th>
					<td>%s</td>
				</tr>',
				esc_attr( $row_id ),
				esc_html( $row_elements[ 'label' ] ),
				$row_elements[ 'value' ]
			);

		}

		printf(
				'<div class="wrap">
					<h2>%s</h2>
					<table class="cf7s-entry">
						%s
					</table>
				</div>',
				esc_html__( 'Form Submission', 'cf7-storage' ),
				implode( '', $rows_html )
			);

	}

}

