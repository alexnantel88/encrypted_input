<?php

	Class FieldEncrypted_Input extends Field {

		function __construct(){
			parent::__construct();
			$this->_name = 'Encrypted Input';
			$this->_required = true;
			$this->set('required', 'yes');
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');
			if($id === false) return false;

			$fields = array();
			$fields['field_id'] = $id;

			Symphony::Database()
				->delete('tbl_fields_' . $this->handle())
				->where(['field_id' => $id])
				->limit(1)
				->execute()
				->success();

			return Symphony::Database()
				->insert('tbl_fields_' . $this->handle())
				->values($fields)
				->execute()
				->success();
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$div = new XMLElement('div', null, array('class' => 'compact'));
			$this->appendRequiredCheckbox($div);
			$wrapper->appendChild($div);
		}

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null){

			$value = General::sanitize($data['value']);
			$label = Widget::Label($this->get('label'));

			if(empty($value)) {
				if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));
				$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, (strlen($value) != 0 ? $value : null)));
				if($flagWithError != null) {
					$wrapper->appendChild(Widget::Error($label, $flagWithError));
				} else {
					$wrapper->appendChild($label);
				}
			} else {
				$wrapper->setAttribute('class', $wrapper->getAttribute('class') . ' file');
				$label->appendChild(new XMLElement('span', $value, array('class' => 'frame')));
				$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, 'encrypted:' . $data['value'], 'hidden'));
				$wrapper->appendChild($label);
			}

		}

		function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null){
			if(!is_array($data) || empty($data['value'])) return;

			$value = $this->decrypt($data['value']);

			$xml = new XMLElement($this->get('element_name'), General::sanitize($value));
			$wrapper->appendChild($xml);
		}

		public function checkPostFieldData($data, &$message, $entry_id = null){
			$message = null;

			if($this->get('required') === 'yes' && strlen($data) == 0){
				$message = __("'%s' is a required field.", array($this->get('label')));
				return self::__MISSING_FIELDS__;
			}

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null) {
			$status = self::__OK__;

			// store empty (null) value without encryption if the field is optional
			if(empty($data)) return array('value' => '');

			// has already been encrypted
			if(preg_match("/^encrypted:/", $data)) {
				return array(
					'value' => preg_replace("/^encrypted:/", '', $data),
				);
			}
			else {
				return array(
					'value' => $this->encrypt($data),
				);
			}

		}

		function encrypt($string) {
			$key = hex2bin('5ae1b8a17bad4da4fdac796f64c16ecd');
			$iv = hex2bin('34857d973953e44afb49ea9d61104d8c');

			return base64_encode(openssl_encrypt(
				$string,
				'AES-256-CBC',
				hash('sha256', Symphony::Configuration()->get('salt', 'encrypted_input')),
				OPENSSL_RAW_DATA,
				$iv
			));
		}

		function decrypt($string) {
			$key = hex2bin('5ae1b8a17bad4da4fdac796f64c16ecd');
			$iv = hex2bin('34857d973953e44afb49ea9d61104d8c');

			return openssl_decrypt(
				base64_decode($string),
				'AES-256-CBC',
				hash('sha256', Symphony::Configuration()->get('salt', 'encrypted_input')),
				OPENSSL_RAW_DATA,
				$iv
			);
		}
	}
