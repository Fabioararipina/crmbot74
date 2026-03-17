{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.1
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
************************************************************************************}

<footer class="app-footer">
        <p>
          Powered by Bot74
        </p>
</footer>
</div>
<div id='overlayPage'>
        <!-- arrow is added to point arrow to the clicked element (Ex:- TaskManagement),
        any one can use this by adding "show" class to it -->
        <div class='arrow'></div>
        <div class='data'>
        </div>
</div>
<div id='helpPageOverlay'></div>
<div id="js_strings" class="hide noprint">{Zend_Json::encode($LANGUAGE_STRINGS)}</div>
<div id="maxListFieldsSelectionSize" class="hide noprint">{$MAX_LISTFIELDS_SELECTION_SIZE}</div>
<div class="modal myModal fade"></div>
{include file='JSResources.tpl'|@vtemplate_path}

<style>
/* ══════════════════════════════════════════════
   Bot74 Theme — vTiger 8.4 — Modern Green UI
   Paleta: #1a9e4a (base) · #25d366 (destaque) · #158a3e (escuro)
   ══════════════════════════════════════════════ */

/* ── 1. MENU LATERAL PRINCIPAL ── */
.row.app-navigator, #app-menu, .app-menu {
    background-color: #1a9e4a !important;
    background: #1a9e4a !important;
}
.menu-item.app-item,
.menu-item.app-item.dropdown-toggle {
    background-color: #1a9e4a !important;
}
.menu-item.app-item:hover,
.menu-item.app-item:focus {
    background-color: #25d366 !important;
}
.app-switcher-container .app-navigator {
    background-color: #1a9e4a !important;
}

/* ── 2. DROPDOWN DE MÓDULOS ── */
.app-menu .app-modules-dropdown,
.dropdown-menu.app-modules-dropdown {
    background-color: #158a3e !important;
    border-color: #25d366 !important;
}
.dropdown-menu.app-modules-dropdown > li > a { color: #ffffff !important; }
.dropdown-menu.app-modules-dropdown > li > a:hover,
.dropdown-menu.app-modules-dropdown > li.selected > a {
    background-color: #25d366 !important;
    color: #ffffff !important;
}

/* ── 3. STRIP DE ÍCONES (42px) ── */
.main-container .module-nav,
.module-nav .modules-menu {
    background-color: #1a9e4a !important;
    background: #1a9e4a !important;
}

/* ── 4. PAINEL LISTAS / FILTROS (240px) ── */
.main-container .sidebar-essentials {
    background: #f0faf4 !important;
    border-right: 2px solid #25d366 !important;
}

/* ── 5. PAINEL CONFIGURAÇÕES ── */
.settings-menu,
.settingsgroup,
.settingsgroup div.panel-collapse,
.settingsgroup .panel-group .panel {
    background: #1a9e4a !important;
    background-color: #1a9e4a !important;
}

/* ── 6. NAVBAR SUPERIOR — linha verde + sombra ── */
.app-fixed-navbar {
    border-bottom: 3px solid #25d366 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.10) !important;
}
.app-nav .module-action-bar {
    border-bottom: 2px solid #e0f4ea !important;
}

/* ── 7. TÍTULO DO MÓDULO ── */
.module-action-bar .module-title,
.module-action-bar .module-breadcrumb a {
    color: #1a9e4a !important;
    font-weight: 600 !important;
}

/* ── 8. CABEÇALHO DA TABELA — verde pastel ── */
.listview-table > thead > tr > th,
.listview-table > thead > tr:first-child > th {
    background-color: #d4f7e3 !important;
    color: #1a9e4a !important;
    font-weight: 700 !important;
    letter-spacing: 0.03em !important;
    border-bottom: 2px solid #25d366 !important;
    padding: 11px 6px !important;
}

/* ── 9. ZEBRA STRIPING NAS LISTAGENS ── */
.listview-table > tbody > tr:nth-child(even) > td {
    background-color: #e8f8f0 !important;
}
.listview-table > tbody > tr:nth-child(odd) > td {
    background-color: #ffffff !important;
}
.listview-table > tbody > tr:hover > td {
    background-color: #c8eedb !important;
    cursor: pointer !important;
}

/* ── 10. LINKS NAS LISTAGENS ── */
.listViewEntries a, .relatedListEntryValues a {
    color: #1a9e4a !important;
}
.listViewEntries a:hover, .relatedListEntryValues a:hover {
    color: #158a3e !important;
    text-decoration: underline !important;
}

/* ── 11. FIELD LABELS NO DETAIL VIEW ── */
.fieldLabel {
    color: #1a9e4a !important;
    font-weight: 600 !important;
}

/* ── 12. BOTÕES — arredondados + verde ── */
.btn, .btn-default {
    border-radius: 6px !important;
}
.btn-success, .btn-primary, .saveButton, .submitButton {
    background-color: #25d366 !important;
    border-color: #1a9e4a !important;
    color: #ffffff !important;
    border-radius: 6px !important;
}
.btn-success:hover, .btn-primary:hover {
    background-color: #1a9e4a !important;
    border-color: #158a3e !important;
}

/* ── 13. INPUTS — bordas suaves + foco verde ── */
input[type="text"], input[type="email"], input[type="number"],
input[type="search"], select, textarea {
    border-radius: 5px !important;
    border-color: #cde8d8 !important;
}
input[type="text"]:focus, input[type="email"]:focus,
input[type="number"]:focus, select:focus, textarea:focus {
    border-color: #25d366 !important;
    box-shadow: 0 0 5px rgba(37,211,102,0.35) !important;
    outline: none !important;
}

/* ── 14. PAINÉIS / CARDS — sombra sutil ── */
.panel, .detailViewBody {
    box-shadow: 0 2px 10px rgba(0,0,0,0.07) !important;
    border-radius: 6px !important;
}

/* ── 15. PAGINAÇÃO ── */
.pagination > li.active > a,
.pagination > li.active > span {
    background-color: #25d366 !important;
    border-color: #1a9e4a !important;
}

/* ── 16. SCROLLBAR PERSONALIZADA (Chrome/Edge) ── */
::-webkit-scrollbar { width: 7px; height: 7px; }
::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
::-webkit-scrollbar-thumb { background: #25d366; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #1a9e4a; }

/* ── 17. FORMULÁRIO EDIT VIEW — visual moderno ── */

/* Card de seção com borda verde no topo */
.fieldBlockContainer {
    border-radius: 8px !important;
    box-shadow: 0 2px 10px rgba(0,0,0,0.07) !important;
    border-top: 3px solid #25d366 !important;
    margin-bottom: 18px !important;
    overflow: hidden !important;
}

/* Cabeçalho da seção ("Informação Lead") — verde uppercase */
h4.fieldBlockHeader {
    color: #1a9e4a !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    letter-spacing: 0.05em !important;
    text-transform: uppercase !important;
    border-bottom: 1px solid #e0f4ea !important;
    padding: 10px 16px !important;
    margin: 0 !important;
    background: #f7fdf9 !important;
}

/* Separador suave entre linhas */
.editViewBody tr {
    border-bottom: 1px solid #f0f0f0 !important;
}
.editViewBody tr:last-child {
    border-bottom: none !important;
}

/* Padding melhorado nas células */
.editViewBody td.fieldLabel {
    padding: 10px 12px 10px 16px !important;
    font-size: 12.5px !important;
}
.editViewBody td.fieldValue {
    padding: 8px 16px 8px 8px !important;
}

/* Inputs — altura, padding e transição suave */
.editViewBody input[type="text"],
.editViewBody input[type="email"],
.editViewBody input[type="number"],
.editViewBody textarea {
    height: 34px !important;
    padding: 6px 10px !important;
    font-size: 13px !important;
    transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
    width: 100% !important;
}
.editViewBody textarea { height: auto !important; min-height: 70px !important; }
</style>

</body>

</html>
