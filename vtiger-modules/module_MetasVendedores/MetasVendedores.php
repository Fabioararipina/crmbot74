<?php
/**
 * MetasVendedores — Classe base do módulo
 * Necessária para o framework vTiger reconhecer o módulo
 */
class MetasVendedores extends CRMEntity {
    public $table_name       = 'vtiger_metasvendedores';
    public $table_index      = 'id';
    public $tab_name         = ['vtiger_metasvendedores'];
    public $tab_name_index   = ['vtiger_metasvendedores' => 'id'];
    public $column_fields    = [];
}
