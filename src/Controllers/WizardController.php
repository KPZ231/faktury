<?php

namespace Dell\Faktury\Controllers;

class WizardController {
    private $pdo;
    private $steps = [
        'basic_info' => 'Podstawowe informacje',
        'agents' => 'Agenci',
        'fees' => 'Opłaty i prowizje',
        'installments' => 'Raty'
    ];

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function showWizard($step = null) {
        // Get current step from URL parameter or default to first step
        $currentStep = $step ?? 'basic_info';
        
        // Validate step
        if (!array_key_exists($currentStep, $this->steps)) {
            $currentStep = 'basic_info';
        }

        // Store current step in session
        $_SESSION['current_step'] = $currentStep;

        // Get step index for navigation
        $stepKeys = array_keys($this->steps);
        $currentStepIndex = array_search($currentStep, $stepKeys);
        
        // Get form data from session if exists
        $formData = $_SESSION['form_data'] ?? [];

        // Pass data to view
        $viewData = [
            'currentStep' => $currentStep,
            'steps' => $this->steps,
            'currentStepIndex' => $currentStepIndex,
            'nextStep' => isset($stepKeys[$currentStepIndex + 1]) ? $stepKeys[$currentStepIndex + 1] : null,
            'prevStep' => isset($stepKeys[$currentStepIndex - 1]) ? $stepKeys[$currentStepIndex - 1] : null,
            'formData' => $formData
        ];

        extract($viewData);
        require_once __DIR__ . '/../Views/wizard.php';
    }

    public function saveRecord() {
        try {
            $data = $_POST;
            
            // Debug submitted data
            error_log("Submitted data: " . print_r($data, true));
            
            // Validate all required fields
            $requiredFields = [
                'Sprawa', 'Zakończona?', 'Wywalczona_kwota',
                'Liczba_agentow', 'Opłata wstępna', 'Success fee',
                'Liczba_rat'
            ];
            
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null || $data[$field] === '0') {
                    error_log("Missing field: $field");
                    error_log("Field value: " . ($data[$field] ?? 'not set'));
                    throw new \Exception("Pole $field jest wymagane");
                }
            }

            // Validate agent commissions
            for ($i = 1; $i <= $data['Liczba_agentow']; $i++) {
                if (empty($data["Prowizja Agent $i"])) {
                    throw new \Exception("Prowizja Agenta $i jest wymagana");
                }
            }

            // Validate installments
            for ($i = 1; $i <= $data['Liczba_rat']; $i++) {
                if (empty($data["Rata $i"])) {
                    throw new \Exception("Rata $i jest wymagana");
                }
            }

            // Prepare SQL statement with all fields
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $sql = "INSERT INTO test2 ($columns) VALUES ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            
            // Bind values
            $i = 1;
            foreach ($data as $value) {
                $stmt->bindValue($i++, $value);
            }

            $stmt->execute();
            
            // Clear session data
            unset($_SESSION['form_data']);
            unset($_SESSION['error']);
            
            // Redirect to success page
            header('Location: /wizard?success=1');
            exit;
        } catch (\Exception $e) {
            // Log error and redirect back with error message
            error_log($e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            header('Location: /wizard');
            exit;
        }
    }

    private function getRequiredFieldsForStep($step) {
        switch ($step) {
            case 'basic_info':
                return ['Sprawa', 'Zakończona?', 'Wywalczona kwota'];
            case 'agents':
                return ['Liczba_agentow'];
            case 'fees':
                return ['Opłata wstępna', 'Success fee'];
            case 'installments':
                return ['Liczba_rat'];
            default:
                return [];
        }
    }
}
