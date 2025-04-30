

-- Create the main commissions table
CREATE TABLE IF NOT EXISTS test2 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_name VARCHAR(255) NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    amount_won DECIMAL(15, 2),
    upfront_fee DECIMAL(15, 2),
    success_fee_percentage DECIMAL(5, 2),
    total_commission DECIMAL(15, 2),
    
    -- Kuba's commission details
    kuba_percentage DECIMAL(5, 2),
    kuba_payout DECIMAL(15, 2),
    
    -- Agent percentages
    agent1_percentage DECIMAL(5, 2),
    agent2_percentage DECIMAL(5, 2),
    agent3_percentage DECIMAL(5, 2),
    agent4_percentage DECIMAL(5, 2),
    agent5_percentage DECIMAL(5, 2),
    
    -- Payment installments
    installment1_amount DECIMAL(15, 2),
    installment1_paid BOOLEAN DEFAULT FALSE,
    installment2_amount DECIMAL(15, 2),
    installment2_paid BOOLEAN DEFAULT FALSE,
    installment3_amount DECIMAL(15, 2),
    installment3_paid BOOLEAN DEFAULT FALSE,
    final_installment_amount DECIMAL(15, 2),
    final_installment_paid BOOLEAN DEFAULT FALSE,
    
    -- Kuba's commission installments
    kuba_installment1_amount DECIMAL(15, 2),
    kuba_invoice_number VARCHAR(50),
    kuba_installment2_amount DECIMAL(15, 2),
    kuba_installment3_amount DECIMAL(15, 2),
    kuba_final_installment_amount DECIMAL(15, 2),
    
    -- Agent 1 commission installments
    agent1_installment1_amount DECIMAL(15, 2),
    agent1_installment2_amount DECIMAL(15, 2),
    agent1_installment3_amount DECIMAL(15, 2),
    agent1_final_installment_amount DECIMAL(15, 2),
    
    -- Agent 2 commission installments
    agent2_installment1_amount DECIMAL(15, 2),
    agent2_installment2_amount DECIMAL(15, 2),
    agent2_installment3_amount DECIMAL(15, 2),
    agent2_final_installment_amount DECIMAL(15, 2),
    
    -- Agent 3 commission installments
    agent3_installment1_amount DECIMAL(15, 2),
    agent3_installment2_amount DECIMAL(15, 2),
    agent3_installment3_amount DECIMAL(15, 2),
    agent3_final_installment_amount DECIMAL(15, 2),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
