<?php

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

return simpleDispatcher(function(RouteCollector $r) {
    $r->addRoute('GET', '/', [Dell\Faktury\Controllers\HomeController::class, 'index']);
    $r->addRoute('POST', '/upload-file', [Dell\Faktury\Controllers\HomeController::class, 'upload']);
    
    // Commission routes
    $r->addRoute('GET', '/get-commission-payments', [Dell\Faktury\Controllers\CommissionController::class, 'getCommissionPaymentsForDisplay']);
    $r->addRoute('GET', '/get-case-agents', [Dell\Faktury\Controllers\AgentController::class, 'getCaseAgents']);
    $r->addRoute('POST', '/update-commission-status', [Dell\Faktury\Controllers\CommissionController::class, 'updateCommissionStatus']);

});