<?php
/**
 * PainelBI — Classe base do módulo
 * Dashboard multi-board/tab com widgets + Construtor de Relatórios
 */
class PainelBI extends CRMEntity {
    public $table_name     = 'vtiger_painelbi_relatorios';
    public $table_index    = 'id';
    public $tab_name       = ['vtiger_painelbi_relatorios'];
    public $tab_name_index = ['vtiger_painelbi_relatorios' => 'id'];
    public $column_fields  = [];
}
