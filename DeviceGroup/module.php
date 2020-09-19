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

		$this->RegisterPropertyBoolean("DimMode",false);
		$this->RegisterPropertyString("DimModeAggregation","MAX");
		$this->RegisterPropertyBoolean("DimModeDisplay",false);
		$this->RegisterPropertyString("DimModeDevices","");

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
		
		// Register Variables if applicable
		if ($this->ReadPropertyBoolean("DimMode") ) {
			
			$this->RegisterVariableBoolean("Intensity","Intensity","~Intensity.100");
			$this->EnableAction("Intensity");
			
			// Register the Message Sinks
			$allDimModeDevices = $this->GetDimModeDevices();
			
			foreach ($allDimModeDevices as $currentDevice) {
				
				$this->RegisterMessage($currentDevice['VariableId'], VM_UPDATE);
			}
			
			if ($this->ReadPropertyBoolean("DimModeDisplay")) {
				
				$this->RegisterVariableString("DevicesDimmed","Devices dimmed","~HTMLBox");
			}
		}
		else {
			
			if (@$this->GetIDForIdent("Intensity")) {
	
				$this->LogMessage("DimMode is inactive and Intensity Variable does exist. It will be unregistered","DEBUG");
				$this->DisableAction("Intensity");
				$this->UnregisterVariable("Intensity");
			}
			else {
				
				$this->LogMessage("Intensity is inactive and Status Variable does not exist.","DEBUG");
			}
			
			if (@$this->GetIDForIdent("DevicesDimmed")) {
				
				$this->UnregisterVariable("DevicesDimmed");
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
							
		$form['elements'][] = Array("type" => "Label", "name" => "DimModeHeading", "caption" => "Dimming mode configuration");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DimMode", "caption" => "Enable Dimming Mode");
		$form['elements'][] = Array(
								"type" => "Select", 
								"name" => "DimModeAggregation", 
								"caption" => "Select Aggregation Mode",
								"options" => Array(
									Array(
										"caption" => "MIN - The lowest intensity in the group is used",
										"value" => "MIN"
									),
									Array(
										"caption" => "MAX - The highest intensity in the group is used",
										"value" => "MAX"
									),
									Array(
										"caption" => "AVG - An average intensity will be calculated from fall devices",
										"value" => "AVG"
									)
								)
							);
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DimModeDisplay", "caption" => "Display dimmed devices in Web Frontend");
		$form['elements'][] = Array(
								"type" => "List", 
								"name" => "DimModeDevices", 
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
										"caption" => "Dimming Order",
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
		$form['actions'][] = Array("type" => "HorizontalSlider", "name" => "IntensityTestSlider", "minimum" => 0, "maximum" => 100, "onChange" => 'DEVGROUP_DimSet($id,$TestIntensity);');

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
		
		if ($this->ReadPropertyBoolean("DimMode")) {
			
			$this->RefreshDimModeDevices();
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
				array_multisort($order, SORT_ASC, $switchModeDevices);
				
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
	
	protected function GetDimModeDevices() {
		
		$dimModeDevicesJson = $this->ReadPropertyString("DimModeDevices");
		$dimModeDevices = json_decode($dimModeDevicesJson, true);
		
		if (is_array($dimModeDevices)) {
			
			if (count($dimModeDevices) != 0) {
				
				$order = array_column($dimModeDevices, 'Order');
				array_multisort($order, SORT_ASC, $dimModeDevices);
				
				return $dimModeDevices;
			}
			else {
				
				return false;
			}
		}
		else {
			
			return false;
		}
	}
	
	protected function RefreshDimModeDevices() {
		
		$allDevices = $this->GetDimModeDevices();
		
		$dimMin = 100;
		$dimMax = 0;
		$dimSum = 0;
		
		$devicesDisplay = "<ul>";
		
		foreach ($allDevices as $currentDevice) {
			
			$varDetails = IPS_GetVariable($currentDevice['VariableId']);
			$currentDimValue = GetValue($currentDevice['VariableId']);
			
			if ( ($varDetails['VariableProfile'] == "~Intensity.255") || ($varDetails['VariableProfile'] == "Intensity.Hue") || ($varDetails['VariableCustomProfile'] == "~Intensity.255") || ($varDetails['VariableCustomProfile'] == "Intensity.Hue") ) {
				
				$currentDimValue = round($currentDimValue / 2.54);
			}
			
			if ($currentDimValue < $dimMin) {
				
				$dimMin = $currentDimValue;
			}
			
			if ($currentDimValue > $dimMax) {
				
				$dimMax = $currentDimValue;
			}
			
			$dimSum += $currentDimValue;
			
			$devicesDisplay .= "<li>" . $currentDimValue . "% - " . $currentDevice['Name'] . "</li>";
		}
		
		$devicesDisplay .= "</ul>";
		$dimAvg = round($dimSum / count($allDevices) );
		
		if ($this->ReadPropertyBoolean("DimModeDisplay")) {
			
			SetValue($this->GetIDForIdent("DevicesDimmed"), $devicesDisplay);
		}
		else {
			
			SetValue($this->GetIDForIdent("DevicesDimmed"), "");
		}
		
		switch ($this->ReadPropertyString("DimModeAggregation")) {
			
			case "MIN":
				SetValue($this->GetIDForIdent("Intensity"), $dimMin);
				break;
			case "MAX":
				SetValue($this->GetIDForIdent("Intensity"), $dimMax);
				break;
			case "AVG":
				SetValue($this->GetIDForIdent("Intensity"), $dimAvg);
				break;
			default:
				$this->LogMessage("Dimming mode has an invalid Aggregation type","ERROR");
		}
	}
	
	public function DimSet($dimLevel) {
		
		if (! $this->ReadPropertyBoolean("DimMode") ) {
			
			$this->LogMessage("Device cannot be dimmed. Dimming mode is inactive");
			return;
		}
		
		$allDimModeDevices = $this->GetDimModeDevices();
		
		foreach($allDimModeDevices as $currentDevice) {
			
			if ( ($varDetails['VariableProfile'] == "~Intensity.255") || ($varDetails['VariableProfile'] == "Intensity.Hue") || ($varDetails['VariableCustomProfile'] == "~Intensity.255") || ($varDetails['VariableCustomProfile'] == "Intensity.Hue") ) {
				
				$dimLevel = round($dimLevel * 2.54);
			}
			
			RequestAction($currentDevice['VariableId'], $dimLevel);
		}
	}
}
