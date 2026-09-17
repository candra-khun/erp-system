<?php

// Konfigurasi modul ERP (PRD Fase 3)
return [
    'loyalty' => [
        // 1 poin per Rp 10.000 belanja
        'points_per_rupiah_divisor' => env('LOYALTY_POINTS_PER_RUPIAH_DIVISOR', 10000),
    ],

    'po_approval' => [
        // PO di atas nominal ini butuh approval level 2 (finance/owner)
        'level1_threshold' => env('PO_APPROVAL_LEVEL1_THRESHOLD', 50000000),
    ],
];
