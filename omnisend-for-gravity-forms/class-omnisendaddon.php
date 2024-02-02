<?php
/**
 * Omnisend Gravity forms add-on
 *
 * @package OmnisendGravityFormsPlugin
 */

use Omnisend\Public\V1\Contact;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

GFForms::include_addon_framework();

class OmnisendAddOn extends GFAddOn {

	protected $_version                  = OMNISEND_GRAVITY_ADDON_VERSION;
	protected $_min_gravityforms_version = '1.9';
	protected $_slug                     = 'omnisend-for-gravity-forms-add-on';
	protected $_path                     = 'omnisend-for-gravity-forms/class-omnisend-addon-bootstrap.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'Omnisend for Gravity Forms';
	protected $_short_title              = 'Omnisend';

	private static $_instance = null;


	public function minimum_requirements() {
		return array(
			'plugins' => array(
				'omnisend/class-omnisend-core-bootstrap.php' => 'Email Marketing by Omnisend',
			),
			array( $this, 'omnisend_connected' ),
		);
	}

	public function omnisend_connected( $meets_requirements ) {
		if ( ! is_plugin_active( 'omnisend/class-omnisend-core-bootstrap.php' ) ) {
			return array( 'meets_requirements' => true ); // Covered with "minimum_requirements" function, no plugin - no need to check more.
		}

		if ( ! class_exists( 'Omnisend\Public\V1\Omnisend' ) ) {
			$meets_requirements['meets_requirements'] = false;
			$meets_requirements['errors'][]           = 'Your Email Marketing by Omnisend is not up to date. Please update plugins';
			return $meets_requirements;
		}

		if ( ! Omnisend\Public\V1\Omnisend::is_connected() ) {
			$meets_requirements['meets_requirements'] = false;
			$meets_requirements['errors'][]           = 'Your Email Marketing by Omnisend is not configured properly. Please configure it firstly';
		}
		return $meets_requirements;
	}

	/**
	 * Get an instance of this class.
	 *
	 * @return OmnisendAddOn
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new OmnisendAddOn();
		}

		return self::$_instance;
	}

	/**
	 * Handles hooks and loading of language files.
	 */
	public function init() {
		parent::init();
		add_action( 'gform_after_submission', array( $this, 'after_submission' ), 10, 2 );
	}


	public function get_form_fields() {
		$form           = $this->get_current_form();
		$all_fields     = array();
		$consent_fields = array();

		foreach ( $form['fields'] as $field ) {
			$inputs = $field->get_entry_inputs();

			if ( $inputs ) {
				$choices = array();

				foreach ( $inputs as $input ) {
					if ( rgar( $input, 'isHidden' ) ) {
						continue;
					}
					$choices[] = array(
						'value' => $input['id'],
						'label' => GFCommon::get_label( $field, $input['id'], true ),
					);
				}

				if ( ! empty( $choices ) ) {
					$all_fields[] = array(
						'choices' => $choices,
						'label'   => GFCommon::get_label( $field ),
					);
				}

				if ( $field->type === 'consent' ) {
					$consent_fields[] = array(
						'choices' => $choices,
						'label'   => GFCommon::get_label( $field ),
					);
				}
			} else {
				$all_fields[] = array(
					'value' => $field->id,
					'label' => GFCommon::get_label( $field ),
				);

				if ( $field->type === 'consent' ) {
					$consent_fields[] = array(
						'value' => $field->id,
						'label' => GFCommon::get_label( $field ),
					);
				}
			}
		}

		return array(
			'allFields'     => $all_fields,
			'consentFields' => $consent_fields,
		);
	}

	public function form_settings_fields( $form ) {
		$fields_data    = $this->get_form_fields();
		$all_fields     = $fields_data['allFields'];
		$consent_fields = $fields_data['consentFields'];

		$choices[] = array(
			'value' => '-1',
			'label' => 'Choose Field',
		);

		$all_fields_choices     = array_merge( $choices, $all_fields );
		$consent_fields_choices = array_merge( $choices, $consent_fields );

		return array(
			array(
				'title'  => esc_html__( 'Welcome Email', 'omnisend-for-gravity-forms-add-on' ),
				'fields' => array(
					array(
						'label'   => esc_html__( 'Check this to automatically send your custom welcome email, created in Omnisend, to subscribers joining through Gravity Forms.', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'checkbox',
						'name'    => 'send_welcome_email_checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Send a welcome email to new subscribers', 'omnisend-for-gravity-forms-add-on' ),
								'name'  => 'send_welcome_email',
							),
						),
					),
					array(
						'type' => 'welcome_automation_details',
						'name' => 'welcome_automation_details',
					),
				),
			),
			array(
				'title'  => esc_html__( 'Omnisend Field Mapping', 'omnisend-for-gravity-forms-add-on' ),

				'fields' => array(
					array(
						'type' => 'field_mapping_details',
						'name' => 'field_mapping_details',
					),
					array(
						'label'               => esc_html__( 'Email', 'omnisend-for-gravity-forms-add-on' ),
						'type'                => 'select',
						'name'                => 'email',
						'validation_callback' => function ( $field, $value ) {
							if ( $value <= 0 ) {
								$field->set_error( esc_html__( 'Email is required', 'omnisend-for-gravity-forms-add-on' ) );
							}
						},
						'choices'             => $all_fields_choices,
					),
					array(
						'label'   => esc_html__( 'Address', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'address',
						'choices' => $all_fields_choices,
					),
					array(
						'label'   => esc_html__( 'City', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'city',
						'choices' => $all_fields_choices,
					),
					array(
						'label'   => esc_html__( 'State', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'state',
						'choices' => $all_fields_choices,
					),
					array(
						'label'   => esc_html__( 'Country', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'country',
						'choices' => $all_fields_choices,
					),
					array(
						'label'   => esc_html__( 'First Name', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'first_name',
						'choices' => $all_fields_choices,
					),
					array(
						'label'   => esc_html__( 'Last Name', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'last_name',
						'choices' => $all_fields_choices,
					),
					array(
						'label'   => esc_html__( 'Phone Number', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'phone_number',
						'choices' => $all_fields_choices,
					),
					array(
						'label'   => esc_html__( 'Birthday', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'birthday',
						'choices' => $all_fields_choices,
					),
					array(
						'label'   => esc_html__( 'Postal Code', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'postal_code',
						'choices' => $all_fields_choices,
					),
					array(
						'label'   => esc_html__( 'Email Consent', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'email_consent',
						'choices' => $consent_fields_choices,
					),
					array(
						'label'   => esc_html__( 'Phone Consent', 'omnisend-for-gravity-forms-add-on' ),
						'type'    => 'select',
						'name'    => 'phone_consent',
						'choices' => $consent_fields_choices,
					),

				),
			),
		);
	}



	/**
	 * Performing a custom action at the end of the form submission process.
	 *
	 * @param array $entry The entry currently being processed.
	 * @param array $form The form currently being processed.
	 */
	public function after_submission( $entry, $form ) {
		if ( ! class_exists( 'Omnisend\Public\V1\Omnisend' ) ) {
			return;
		}

		try {
			$contact  = new Contact();
			$settings = $this->get_form_settings( $form );

			$fields_to_process = array(
				'email',
				'address',
				'country',
				'city',
				'state',
				'first_name',
				'last_name',
				'birthday',
				'phone_number',
				'postal_code',
				'email_consent',
				'phone_consent',
			);

			$email         = '';
			$phone_number  = '';
			$postal_code   = '';
			$address       = '';
			$country       = '';
			$city          = '';
			$state         = '';
			$first_name    = '';
			$last_name     = '';
			$birthday      = '';
			$email_consent = false;
			$phone_consent = false;

			foreach ( $fields_to_process as $field ) {

				if ( isset( $settings[ $field ] ) && $settings[ $field ] != '-1' ) {
					if ( in_array( $field, array( 'email_consent', 'phone_consent' ) ) ) {
						if ( $entry[ $settings[ $field ] ] == '1' ) {
							${$field} = true;
						}
					} else {
						${$field} = $entry[ $settings[ $field ] ];
					}
				}
			}

			if ( $email == '' ) {
				return; // Email is not mapped. Skipping Omnisend contact creation.
			}

			$contact->set_email( $email );

			if ( $phone_number != '' ) {
				$contact->set_phone( $phone_number );
			}

			$contact->set_first_name( $first_name );
			$contact->set_last_name( $last_name );
			$contact->set_birthday( $birthday );
			$contact->set_postal_code( $postal_code );
			$contact->set_address( $address );
			$contact->set_state( $state );
			$contact->set_country( $country );
			$contact->set_city( $city );
			$contact->add_tag( 'gravity_forms' );
			$contact->add_tag( 'gravity_forms ' . $form['title'] );

			if ( $email_consent ) {
				$contact->set_email_consent( 'gravity-forms' );
				$contact->set_email_opt_in( 'gravity-forms' );
			}

			if ( $phone_consent ) {
				$contact->set_phone_consent( 'gravity-forms' );
				$contact->set_phone_opt_in( 'gravity-forms' );
			}

			if ( isset( $settings['send_welcome_email'] ) && $settings['send_welcome_email'] == '1' ) {
				$contact->set_welcome_email( true );
			}

			$this->mapCustomProperties( $form, $entry, $settings, $contact );

			$response = \Omnisend\Public\V1\Omnisend::get_client( OMNISEND_GRAVITY_ADDON_NAME, OMNISEND_GRAVITY_ADDON_VERSION )->create_contact( $contact );
			error_log( print_r( $response, 1 ) );
			if ( ! is_string( $response ) ) {
				return;
			}

			$this->enableWebTracking( $email, $phone_number );

		} catch ( Exception $e ) {
			error_log( 'Error in after_submission: ' . $e->getMessage() ); // todo check if it is possible to get exception? If not remove handling
		}
	}

	private function mapCustomProperties( $form, $entry, $settings, Contact $contact ) {
		$prefix = 'gravity_forms_';
		foreach ( $form['fields'] as $field ) {
			$field_id    = $field['id'];
			$field_label = $field['label'];

			if ( ! in_array( $field_id, $settings ) || $settings[ array_search( $field_id, $settings ) ] === '-1' ) {
				// Replace spaces with underscores, remove invalid characters, lowercase.
				$safe_label = strtolower( str_replace( ' ', '_', $field_label ) );

				if ( $field['type'] !== 'checkbox' ) {
					// Check if the value is set and not empty.
					if ( ! empty( $entry[ $field_id ] ) ) {
						$contact->add_custom_property( $prefix . $safe_label, $entry[ $field_id ] );
					}
				} else {
					$selected_choices = array();
					if ( isset( $field['inputs'] ) && is_array( $field['inputs'] ) ) {
						foreach ( $field['inputs'] as $input ) {
							$choice_id = $input['id'];
							if ( ! empty( $entry[ $choice_id ] ) ) {
								$selected_choices[] = $input['label'];
							}
						}
					}
					// Only add to customProperties if selectedChoices is not empty.
					if ( ! empty( $selected_choices ) ) {
						$contact->add_custom_property( $prefix . $safe_label, $selected_choices );
					}
				}
			}
		}
	}

	public function get_menu_icon() {
		return file_get_contents( $this->get_base_path() . '/images/menu-icon.svg' );
	}

	private function enableWebTracking( $email, $phone ) {
		$identifiers = array_filter(
			array(
				'email' => sanitize_email( $email ),
				'phone' => sanitize_text_field( $phone ),
			)
		);

		$path_to_script = plugins_url( '/js/snippet.js', __FILE__ );

		wp_enqueue_script( 'omnisend-snippet-script', $path_to_script, array(), '1.0.0', true );
		wp_localize_script( 'omnisend-snippet-script', 'omnisendIdentifiers', $identifiers );
	}

	public function settings_welcome_automation_details( $field, $echo = true ) {
		echo '<div class="gform-settings-field">' . esc_html__( 'After checking this, don’t forget to design your welcome email in Omnisend.', 'omnisend-for-gravity-forms-add-on' ) . '</div>';
		echo '<a target="_blank" href="https://support.omnisend.com/en/articles/1061818-welcome-email-automation">' . esc_html__( 'Learn more about Welcome automation', 'omnisend-for-gravity-forms-add-on' ) . '</a>';
	}


	public function settings_field_mapping_details() {
		echo '<div class="gform-settings-field">' . esc_html__( 'Field mapping lets you align your form fields with Omnisend. It\'s important to match them correctly, so the information collected through Gravity Forms goes into the right place in Omnisend.', 'omnisend-for-gravity-forms-add-on' ) . '</div>';

		echo '<img width="900" src="' . plugins_url( '/images/omnisend-field-mapping.png', __FILE__ ) . '" alt="Omnisend Field Mapping" />';

		echo '<div class="alert gforms_note_info">' . esc_html__( 'Having trouble? Explore our help article.', 'omnisend-for-gravity-forms-add-on' ) . '<br/><a target="_blank" href="https://support.omnisend.com/en/articles/8617559-integration-with-gravity-forms">' . esc_html__( 'Learn more', 'omnisend-for-gravity-forms-add-on' ) . '</a></div>';
	}
}
