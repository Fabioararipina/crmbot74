<?php
/*
 * crmbot74 — Override de Leads_DetailView_Model
 * Adiciona painéis do Prime Atende e Bot74 na tela de detalhe do Lead.
 * Montado em: /var/www/html/modules/Leads/models/DetailView.php
 */

require_once 'modules/Accounts/models/DetailView.php';

class Leads_DetailView_Model extends Accounts_DetailView_Model {

    /**
     * Adiciona os widgets do ModComments, Prime Atende e Bot74 à lista padrão de widgets.
     */
    public function getWidgets() {
        $widgets  = parent::getWidgets(); // Documents, Updates (via Vtiger base)
        $recordId = $this->getRecord()->getId();
        $modName  = $this->getModuleName(); // "Leads"

        // Força o widget de comentários (ModComments) mesmo que isCommentEnabled falhe
        $hasComments = false;
        foreach ($widgets as $w) {
            if ($w->get('linklabel') === 'ModComments') {
                $hasComments = true;
                break;
            }
        }
        if (!$hasComments) {
            array_unshift($widgets, Vtiger_Link_Model::getInstanceFromValues([
                'linktype' => 'DETAILVIEWWIDGET',
                'linklabel' => 'ModComments',
                'linkurl'  => 'module=' . $modName . '&view=Detail&record=' . $recordId . '&mode=showRecentComments&page=1&limit=5',
            ]));
        }

        $widgets[] = Vtiger_Link_Model::getInstanceFromValues([
            'linktype' => 'DETAILVIEWWIDGET',
            'linklabel' => 'Prime Atende',
            'linkurl'  => 'module=' . $modName . '&view=Detail&record=' . $recordId . '&mode=showPrimeAtendeWidget',
        ]);

        $widgets[] = Vtiger_Link_Model::getInstanceFromValues([
            'linktype' => 'DETAILVIEWWIDGET',
            'linklabel' => 'Bot74',
            'linkurl'  => 'module=' . $modName . '&view=Detail&record=' . $recordId . '&mode=showBot74Widget',
        ]);

        return $widgets;
    }

    /**
     * getDetailViewLinks — idêntico ao original do vTiger.
     * (Copiado para evitar quebra de herança ao montar o arquivo via volume.)
     */
    public function getDetailViewLinks($linkParams) {
        $currentUserModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();

        $moduleModel = $this->getModule();
        $recordModel = $this->getRecord();
        $emailModuleModel = Vtiger_Module_Model::getInstance('Emails');

        $baseDetailViewModel = new Vtiger_DetailView_Model();
        $baseDetailViewModel->setModule($moduleModel);
        $baseDetailViewModel->setRecord($recordModel);

        $linkModelList = $baseDetailViewModel::getDetailViewLinks($linkParams);

        // Garante que nossos widgets customizados (ModComments, Prime Atende, Bot74) estejam na lista
        foreach ($this->getWidgets() as $widget) {
            $label = $widget->get('linklabel');
            $alreadyAdded = false;
            foreach (($linkModelList['DETAILVIEWWIDGET'] ?? []) as $existing) {
                if ($existing->get('linklabel') === $label) {
                    $alreadyAdded = true;
                    break;
                }
            }
            if (!$alreadyAdded) {
                $linkModelList['DETAILVIEWWIDGET'][] = $widget;
            }
        }

        if ($currentUserModel->hasModulePermission($emailModuleModel->getId())) {
            $basicActionLink = [
                'linktype' => 'DETAILVIEWBASIC',
                'linklabel' => 'LBL_SEND_EMAIL',
                'linkurl'  => 'javascript:Vtiger_Detail_Js.triggerSendEmail("index.php?module=' . $this->getModule()->getName() .
                    '&view=MassActionAjax&mode=showComposeEmailForm&step=step1","Emails");',
                'linkicon' => '',
            ];
            $linkModelList['DETAILVIEWBASIC'][] = Vtiger_Link_Model::getInstanceFromValues($basicActionLink);
        }

        $index = 0;
        foreach ($linkModelList['DETAILVIEW'] as $link) {
            if ($link->linklabel == 'View History' || $link->linklabel == 'Send SMS') {
                unset($linkModelList['DETAILVIEW'][$index]);
            } elseif ($link->linklabel == 'LBL_SHOW_ACCOUNT_HIERARCHY') {
                $linkURL = 'index.php?module=Accounts&view=AccountHierarchy&record=' . $recordModel->getId();
                $link->linkurl = 'javascript:Accounts_Detail_Js.triggerAccountHierarchy("' . $linkURL . '");';
                unset($linkModelList['DETAILVIEW'][$index]);
                $linkModelList['DETAILVIEW'][$index] = $link;
            }
            $index++;
        }

        $CalendarActionLinks = [];
        $CalendarModuleModel = Vtiger_Module_Model::getInstance('Calendar');
        if ($currentUserModel->hasModuleActionPermission($CalendarModuleModel->getId(), 'CreateView')) {
            $CalendarActionLinks[] = [
                'linktype' => 'DETAILVIEW',
                'linklabel' => 'LBL_ADD_EVENT',
                'linkurl'  => $recordModel->getCreateEventUrl(),
                'linkicon' => '',
            ];
            $CalendarActionLinks[] = [
                'linktype' => 'DETAILVIEW',
                'linklabel' => 'LBL_ADD_TASK',
                'linkurl'  => $recordModel->getCreateTaskUrl(),
                'linkicon' => '',
            ];
        }

        $SMSNotifierModuleModel = Vtiger_Module_Model::getInstance('SMSNotifier');
        if ($SMSNotifierModuleModel && $currentUserModel->hasModulePermission($SMSNotifierModuleModel->getId())) {
            $basicActionLink = [
                'linktype' => 'DETAILVIEWBASIC',
                'linklabel' => 'LBL_SEND_SMS',
                'linkurl'  => 'javascript:Vtiger_Detail_Js.triggerSendSms("index.php?module=' . $this->getModule()->getName() .
                    '&view=MassActionAjax&mode=showSendSMSForm","SMSNotifier");',
                'linkicon' => '',
            ];
            $linkModelList['DETAILVIEW'][] = Vtiger_Link_Model::getInstanceFromValues($basicActionLink);
        }

        foreach ($CalendarActionLinks as $basicLink) {
            $linkModelList['DETAILVIEW'][] = Vtiger_Link_Model::getInstanceFromValues($basicLink);
        }

        if (
            Users_Privileges_Model::isPermitted($moduleModel->getName(), 'ConvertLead', $recordModel->getId()) &&
            Users_Privileges_Model::isPermitted($moduleModel->getName(), 'EditView', $recordModel->getId()) &&
            !$recordModel->isLeadConverted()
        ) {
            $basicActionLink = [
                'linktype' => 'DETAILVIEWBASIC',
                'linklabel' => 'LBL_CONVERT_LEAD',
                'linkurl'  => 'Javascript:Leads_Detail_Js.convertLead("' . $recordModel->getConvertLeadUrl() . '",this);',
                'linkicon' => '',
            ];
            $linkModelList['DETAILVIEWBASIC'][] = Vtiger_Link_Model::getInstanceFromValues($basicActionLink);
        }

        return $linkModelList;
    }
}
