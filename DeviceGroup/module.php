<?php

// Klassendefinition
class DeviceGroup extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","DeviceGroup");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		
		$this->RegisterPropertyBoolean("SwitchMode",false);
		$this->RegisterPropertyString("SwitchModeAggregation","ALLOFF");
		$this->RegisterPropertyBoolean("SwitchModeDisplay",false);
		$this->RegisterPropertyString("SwitchModeDevices","");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'DEVGROUP_RefreshInformation($_IPS[\'TARGET\']);');
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {

		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
		// Register Variables if applicable
		if ($this->ReadPropertyBoolean("SwitchMode") ) {
			
			$this->RegisterVariableBoolean("Status","Status","~Switch");
			$this->EnableAction("Status");
			
			// Register the Message Sinks
			$allSwitchModeDevices = $this->GetSwitchModeDevices();
			
			foreach ($allSwitchModeDevices as $currentDevice) {
				
				$this->RegisterMessage($currentDevice['VariableId'], VM_UPDATE);
			}
			
			if ($this->ReadPropertyBoolean("SwitchModeDisplay")) {
				
				$this->RegisterVariableString("DevicesSwitchedOn","Devices switched on","~HTMLBox");
			}
		}
		else {
			
			if (@$this->GetIDForIdent("Status")) {
	
				$this->LogMessage("SwitchMode is inactive and Status Variable does exist. It will be unregistered","DEBUG");
				$this->DisableAction("Status");
				$this->UnregisterVariable("Status");
			}
			else {
				
				$this->LogMessage("SwitchMode is inactive and Status Variable does not exist.","DEBUG");
			}
			
			if (@$this->GetIDForIdent("DevicesSwitchedOn")) {
				
				$this->UnregisterVariable("DevicesSwitchedOn");
			}
		}
			
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}


	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		
		$form['elements'][] = Array("type" => "Label", "name" => "SwitchModeHeading", "caption" => "Switch mode configuration");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "SwitchMode", "caption" => "Enable Switch Mode");
		$form['elements'][] = Array(
								"type" => "Select", 
								"name" => "SwitchModeAggregation", 
								"caption" => "Select Aggregation Mode",
								"options" => Array(
									Array(
										"caption" => "ALLOFF - Status is only off if all devices are off",
										"value" => "ALLOFF"
									),
									Array(
										"caption" => "ALLON - Status is only on if all devices are on",
										"value" => "ALLON"
									)
								)
							);
		$form['elements'][] = Array("type" => "CheckBox", "name" => "SwitchModeDisplay", "caption" => "Display Switched on devices in Web Frontend");
		$form['elements'][] = Array(
								"type" => "List", 
								"name" => "SwitchModeDevices", 
								"caption" => "Device Status variables",
								"rowCount" => 5,
								"add" => true,
								"delete" => true,
								"columns" => Array(
									Array(
										"caption" => "Variable Id",
										"name" => "VariableId",
										"width" => "350px",
										"edit" => Array("type" => "SelectVariable"),
										"add" => 0
									),
									Array(
										"caption" => "Name",
										"name" => "Name",
										"width" => "250px",
										"edit" => Array("type" => "ValidationTextBox"),
										"add" => "Display Name"
									),
									Array(
										"caption" => "Switching Order",
										"name" => "Order",
										"width" => "auto",
										"edit" => Array("type" => "NumberSpinner"),
										"add" => 1
									)
								)
							);
		
		
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'DEVGROUP_RefreshInformation($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Switch On", "onClick" => 'DEVGROUP_SwitchOn($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Switch Off", "onClick" => 'DEVGROUP_SwitchOff($id);');

		// Return the completed form
		return json_encode($form);

	}
	
	protected function LogMessage($message, $severity = 'INFO') {
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		
		IPS_LogMessage($this->ReadPropertyString('Sender') . " - " . $this->InstanceID, $messageComplete);
	}

	public function RefreshInformation() {

		$this->LogMessage("Refresh in Progress", "DEBUG");
		
		if ($this->ReadPropertyBoolean("SwitchMode")) {
			
			$this->RefreshSwitchModeDevices();
		}
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				if (GetValue($this->GetIDForIdent("Status"))) {
					
					$this->SwitchOff();
				} 
				else {
					
					$this->SwitchOn();
				}
				break;
			default:
				throw new Exception("Invalid Ident");
		}
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		// $this->LogMessage("$TimeStamp - $SenderId - $Message", "DEBUG");
		
		$this->RefreshInformation();
	}
	
	protected function GetSwitchModeDevices() {
		
		$switchModeDevicesJson = $this->ReadPropertyString("SwitchModeDevices");
		$switchModeDevices = json_decode($switchModeDevicesJson, true);
		
		if (is_array($switchModeDevices)) {
			
			if (count($switchModeDevices) != 0) {
				
				$order = array_column($switchModeDevices, 'Order');
				array_multisort($order, SORT_ASC, switchModeDevices);
				
				return $switchModeDevices;
			}
			else {
				
				return false;
			}
		}
		else {
			
			return false;
		}
	}
	
	protected function RefreshSwitchModeDevices() {
		
		$allDevices = $this->GetSwitchModeDevices();
		
		$devicesOn = 0;
		$devicesDisplay = "<ul>";
		
		foreach ($allDevices as $currentDevice) {
			
			if (GetValue($currentDevice['VariableId']) ) {
				
				$devicesOn++;
				$devicesDisplay .= "<li>" . $currentDevice['Name'] . "</li>";
			}
		}
		
		$devicesDisplay .= "</ul>";
		
		if ($this->ReadPropertyBoolean("SwitchModeDisplay")) {
			
			SetValue($this->GetIDForIdent("DevicesSwitchedOn"), $devicesDisplay);
		}
		else {
			
			SetValue($this->GetIDForIdent("DevicesSwitchedOn"), "");
		}
		
		switch ($this->ReadPropertyString("SwitchModeAggregation")) {
			
			case "ALLOFF":
				if ($devicesOn > 0) {
					
					SetValue($this->GetIDForIdent("Status"), true);
				}
				else {
					
					SetValue($this->GetIDForIdent("Status"), false);
				}
				break;
			case "ALLON":
				if ($devicesOn == count($allDevices)) {
					
					SetValue($this->GetIDForIdent("Status"), true);
				}
				else {
					
					SetValue($this->GetIDForIdent("Status"), false);
				}
				break;
			default:
				$this->LogMessage("Switch mode has an invalid Aggregation type","ERROR");
		}
	}
	
	public function SwitchOn() {
		
		if (! $this->ReadPropertyBoolean("SwitchMode") ) {
			
			$this->LogMessage("Device cannot be switched on. Switch mode is inactive");
			return;
		}
		
		$allSwitchModeDevices = $this->GetSwitchModeDevices();
		
		foreach($allSwitchModeDevices as $currentDevice) {
			
			if (! GetValue($currentDevice['VariableId']) ) {
				
				RequestAction($currentDevice['VariableId'], true);
				$this->LogMessage("Switching on " . $currentDevice['Name'], "DEBUG");
			}
		}
	}

	public function SwitchOff() {
		
		if (! $this->ReadPropertyBoolean("SwitchMode") ) {
			
			$this->LogMessage("Device cannot be switched on. Switch mode is inactive");
			return;
		}
		
		$allSwitchModeDevices = $this->GetSwitchModeDevices();
		
		foreach($allSwitchModeDevices as $currentDevice) {
			
			if (GetValue($currentDevice['VariableId']) ) {
				
				RequestAction($currentDevice['VariableId'], false);
				$this->LogMessage("Switching off " . $currentDevice['Name'], "DEBUG");
			}
		}
	}
}
