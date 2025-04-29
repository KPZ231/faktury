<?php

namespace Dell\Faktury\Models;

class User {
    /**
     * Przykładowe dane użytkowników
     * @return array
     */
    public static function all(): array {
        return [
            ['id' => 1, 'name' => 'Jan Kowalski'],
            ['id' => 2, 'name' => 'Anna Nowak'],
        ];
    }
}