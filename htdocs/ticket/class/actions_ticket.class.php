<?php
/* Copyright (C) 2013-2015 Jean-François FERRY <hello@librethic.io>
 * Copyright (C) 2016      Christophe Battarel <christophe@altairis.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *    \file       ticket/class/actions_ticket.class.php
 *    \ingroup    ticket
 *    \brief      File Class ticket
 */

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT . '/fichinter/class/fichinter.class.php';


/**
 *  Class Actions of the module ticket
 */
class ActionsTicket
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    public $dao;

    public $mesg;

    /**
     * @var string Error code (or message)
     */
    public $error;

    /**
     * @var string[] Error codes (or messages)
     */
    public $errors = array();

    //! Numero de l'erreur
    public $errno = 0;

    public $template_dir;
    public $template;

    /**
     * @var string ticket action label
     */
    public $label;

    /**
     * @var string description
     */
    public $description;

    /**
     * @var int ID
     */
    public $fk_statut;

    /**
     * @var int Thirdparty ID
     */
    public $fk_soc;

    /**
     *    Constructor
     *
     *    @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Instantiation of DAO class
     *
     * @return void
     */
    public function getInstanceDao()
    {
        if (!is_object($this->dao)) {
            $this->dao = new Ticket($this->db);
        }
    }

    /**
     * Fetch object
     *
     * @param	int		$id				ID of ticket
     * @param	string	$ref			Reference of ticket
     * @param	string	$track_id		Track ID of ticket (for public area)
     * @return 	void
     */
    public function fetch($id = 0, $ref = '', $track_id = '')
    {
        $this->getInstanceDao();
        return $this->dao->fetch($id, $ref, $track_id);
    }

    /**
     * Print statut
     *
     * @param		int		$mode		Display mode
     * @return 		string				Label of status
     */
    public function getLibStatut($mode = 0)
    {
        $this->getInstanceDao();
        $this->dao->fk_statut = $this->fk_statut;
        return $this->dao->getLibStatut($mode);
    }

    /**
     * Get ticket info
     *
     * @param  int $id    Object id
     * @return void
     */
    public function getInfo($id)
    {
        $this->getInstanceDao();
        $this->dao->fetch($id, '', $track_id);

        $this->label = $this->dao->label;
        $this->description = $this->dao->description;
    }

    /**
     * Get action title
     *
     * @param string 	$action    	Type of action
     * @return string			Title of action
     */
    public function getTitle($action = '')
    {
        global $langs;

        if ($action == 'create') {
            return $langs->trans("CreateTicket");
        } elseif ($action == 'edit') {
            return $langs->trans("EditTicket");
        } elseif ($action == 'view') {
            return $langs->trans("TicketCard");
        } elseif ($action == 'add_message') {
            return $langs->trans("AddMessage");
        } else {
            return $langs->trans("TicketsManagement");
        }
    }

    /**
     * View html list of logs
     *
     * @param boolean $show_user Show user who make action
     * @return void
     */
    public function viewTicketLogs($show_user = true)
    {
        global $conf, $langs;

        // Load logs in cache
        $ret = $this->dao->loadCacheLogsTicket();

        if (is_array($this->dao->cache_logs_ticket) && count($this->dao->cache_logs_ticket) > 0) {
            print '<table class="border" style="width:100%;">';

            print '<tr class="liste_titre">';

            print '<th>';
            print $langs->trans('DateCreation');
            print '</th>';

            if ($show_user) {
                print '<th>';
                print $langs->trans('User');
                print '</th>';
            }

            foreach ($this->dao->cache_logs_ticket as $id => $arraylogs) {
                print '<tr class="oddeven">';
                print '<td><strong>';
                print dol_print_date($arraylogs['datec'], 'dayhour');
                print '</strong></td>';

                if ($show_user) {
                    print '<td>';
                    if ($arraylogs['fk_user_create'] > 0) {
                        $userstat = new User($this->db);
                        $res = $userstat->fetch($arraylogs['fk_user_create']);
                        if ($res) {
                            print $userstat->getNomUrl(1);
                        }
                    }
                    print '</td>';
                }
                print '</tr>';
                print '<tr class="oddeven">';
                print '<td colspan="2">';
                print dol_nl2br($arraylogs['message']);

                print '</td>';
                print '</tr>';
            }

            print '</table>';
        } else {
            print '<div class="info">' . $langs->trans('NoLogForThisTicket') . '</div>';
        }
    }

    /**
     * View list of logs with timeline view
     *
     * @param 	boolean 	$show_user 	Show user who make action
     * @param	Ticket	$object		Object
     * @return void
     */
    public function viewTimelineTicketLogs($show_user = true, $object = true)
    {
        global $conf, $langs;

        // Load logs in cache
        $ret = $object->loadCacheLogsTicket();

        if (is_array($object->cache_logs_ticket) && count($object->cache_logs_ticket) > 0) {
            print '<section id="cd-timeline">';

            foreach ($object->cache_logs_ticket as $id => $arraylogs) {
                print '<div class="cd-timeline-block">';
                print '<div class="cd-timeline-img">';
                //print '<img src="img/history.png" alt="">';
                print '</div> <!-- cd-timeline-img -->';

                print '<div class="cd-timeline-content">';
                print dol_nl2br($arraylogs['message']);

                print '<span class="cd-date">';
                print dol_print_date($arraylogs['datec'], 'dayhour');

                if ($show_user) {
                    if ($arraylogs['fk_user_create'] > 0) {
                        $userstat = new User($this->db);
                        $res = $userstat->fetch($arraylogs['fk_user_create']);
                        if ($res) {
                            print '<br><small>'.$userstat->getNomUrl(1).'</small>';
                        }
                    }
                }
                print '</span>';
                print '</div> <!-- cd-timeline-content -->';
                print '</div> <!-- cd-timeline-block -->';
            }
            print '</section>';
        } else {
            print '<div class="info">' . $langs->trans('NoLogForThisTicket') . '</div>';
        }
    }

    /**
     * Show ticket original message
     *
     * @param 	User		$user		User wich display
     * @param 	string 		$action    	Action mode
     * @param	Ticket	$object		Object ticket
     * @return	void
     */
    public function viewTicketOriginalMessage($user, $action, $object)
    {
        global $langs;
        if (!empty($user->rights->ticket->manage) && $action == 'edit_message_init') {
            // MESSAGE

            print '<form action="' . $_SERVER['PHP_SELF'] . '" method="post">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            print '<input type="hidden" name="track_id" value="' . $object->track_id . '">';
            print '<input type="hidden" name="action" value="set_message">';
        }

        // Initial message
        print '<div class="underbanner clearboth"></div>';
        print '<div class="div-table-responsive-no-min">';		// You can use div-table-responsive-no-min if you dont need reserved height for your table
        print '<table class="border centpercent margintable">';
        print '<tr class="liste_titre"><td class="nowrap titlefield">';
        print $langs->trans("InitialMessage");
        print '</td><td>';
        if ($user->rights->ticket->manage) {
            print '<a  href="' . $_SERVER['PHP_SELF'] . '?action=edit_message_init&amp;track_id=' . $object->track_id . '">' . img_edit($langs->trans('Modify')) . '</a>';
        }
        print '</td></tr>';

        print '<tr>';
        print '<td colspan="2">';
        if (!empty($user->rights->ticket->manage) && $action == 'edit_message_init') {
            // MESSAGE
            $msg = GETPOST('message_initial', 'alpha') ? GETPOST('message_initial', 'alpha') : $object->message;
            include_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
            $uselocalbrowser = true;
            $doleditor = new DolEditor('message_initial', $msg, '100%', 250, 'dolibarr_details', 'In', true, $uselocalbrowser);
            $doleditor->Create();
        } else {
            // Deal with format differences (text / HTML)
            if (dol_textishtml($object->message)) {
                print $object->message;
            } else {
                print dol_nl2br($object->message);
            }

            //print '<div>' . $object->message . '</div>';
        }
        print '</td>';
        print '</tr>';
        print '</table>';
        if ($user->rights->ticket->manage && $action == 'edit_message_init') {
            print ' <input type="submit" class="button" value="' . $langs->trans('Modify') . '">';
            print ' <input type="submit" class="button" name="cancel" value="' . $langs->trans('Cancel') . '">';
            print '</form>';
        }
    }
    /**
     * View html list of message for ticket
     *
     * @param boolean $show_private Show private messages
     * @param boolean $show_user    Show user who make action
     * @return void
     */
    public function viewTicketMessages($show_private, $show_user = true)
    {
        global $conf, $langs, $user;

        // Load logs in cache
        $ret = $this->dao->loadCacheMsgsTicket();
        $action = GETPOST('action');

        $this->viewTicketOriginalMessage($user, $action);

        if (is_array($this->dao->cache_msgs_ticket) && count($this->dao->cache_msgs_ticket) > 0) {
            print load_fiche_titre($langs->trans('TicketMailExchanges'));

            print '<table class="border" style="width:100%;">';

            print '<tr class="liste_titre">';

            print '<td>';
            print $langs->trans('DateCreation');
            print '</td>';

            if ($show_user) {
                print '<td>';
                print $langs->trans('User');
                print '</td>';
            }

            foreach ($this->dao->cache_msgs_ticket as $id => $arraymsgs) {
                if (!$arraymsgs['private']
                    || ($arraymsgs['private'] == "1" && $show_private)
                ) {
                    //print '<tr>';
                    print '<tr class="oddeven">';
                    print '<td><strong>';
                    print dol_print_date($arraymsgs['datec'], 'dayhour');
                    print '<strong></td>';
                    if ($show_user) {
                        print '<td>';
                        if ($arraymsgs['fk_user_action'] > 0) {
                            $userstat = new User($this->db);
                            $res = $userstat->fetch($arraymsgs['fk_user_action']);
                            if ($res) {
                                print $userstat->getNomUrl(0);
                            }
                        } else {
                            print $langs->trans('Customer');
                        }
                        print '</td>';
                    }
                    print '</td>';
                    print '<tr class="oddeven">';
                    print '<td colspan="2">';
                    print $arraymsgs['message'];
                    print '</td>';
                    print '</tr>';
                }
            }

            print '</table>';
        } else {
            print '<div class="info">' . $langs->trans('NoMsgForThisTicket') . '</div>';
        }
    }

    /**
     * View list of message for ticket with timeline display
     *
     * @param 	boolean 	$show_private Show private messages
     * @param 	boolean 	$show_user    Show user who make action
     * @param	Ticket	$object		 Object ticket
     * @return void
     */
    public function viewTicketTimelineMessages($show_private, $show_user, Ticket $object)
    {
        global $conf, $langs, $user;

        // Load logs in cache
        $ret = $object->loadCacheMsgsTicket();
        $action = GETPOST('action');

        if (is_array($object->cache_msgs_ticket) && count($object->cache_msgs_ticket) > 0) {
            print '<section id="cd-timeline">';

            foreach ($object->cache_msgs_ticket as $id => $arraymsgs) {
                if (!$arraymsgs['private']
                || ($arraymsgs['private'] == "1" && $show_private)
                ) {
                    print '<div class="cd-timeline-block">';
                    print '<div class="cd-timeline-img">';
                    print '<img src="img/messages.png" alt="">';
                    print '</div> <!-- cd-timeline-img -->';

                    print '<div class="cd-timeline-content">';
                    print $arraymsgs['message'];

                    print '<span class="cd-date">';
                    print dol_print_date($arraymsgs['datec'], 'dayhour');

                    if ($show_user) {
                        if ($arraymsgs['fk_user_action'] > 0) {
                            $userstat = new User($this->db);
                            $res = $userstat->fetch($arraymsgs['fk_user_action']);
                            if ($res) {
                                print '<br>';
                                print $userstat->getNomUrl(1);
                            }
                        } else {
                            print '<br>';
                            print $langs->trans('Customer');
                        }
                    }
                    print '</span>';
                    print '</div> <!-- cd-timeline-content -->';
                    print '</div> <!-- cd-timeline-block -->';
                }
            }
            print '</section>';
        } else {
            print '<div class="info">' . $langs->trans('NoMsgForThisTicket') . '</div>';
        }
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     * load_previous_next_ref
     *
     * @param string		$filter			Filter
     * @param int			$fieldid		Id
     * @return int			0
     */
    public function load_previous_next_ref($filter, $fieldid)
    {
        // phpcs:enable
        $this->getInstanceDao();
        return $object->load_previous_next_ref($filter, $fieldid);
    }

    /**
     * Send ticket by email to linked contacts
     *
     * @param string $subject          Email subject
     * @param string $message          Email message
     * @param int    $send_internal_cc Receive a copy on internal email ($conf->global->TICKET_NOTIFICATION_EMAIL_FROM)
     * @param array  $array_receiver   Array of receiver. exemple array('name' => 'John Doe', 'email' => 'john@doe.com', etc...)
     * @return void
     */
    public function sendTicketMessageByEmail($subject, $message, $send_internal_cc = 0, $array_receiver = array())
    {
        global $conf, $langs;

        if ($conf->global->TICKET_DISABLE_ALL_MAILS) {
            dol_syslog(get_class($this) . '::sendTicketMessageByEmail: Emails are disable into ticket setup by option TICKETSUP_DISABLE_ALL_MAILS', LOG_WARNING);
            return '';
        }

        $langs->load("mails");

        if (!class_exists('Contact')) {
            include_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
        }

        $contactstatic = new Contact($this->db);

        // If no receiver defined, load all ticket linked contacts
        if (!is_array($array_receiver) || !count($array_receiver) > 0) {
            $array_receiver = $object->getInfosTicketInternalContact();
            $array_receiver = array_merge($array_receiver, $object->getInfosTicketExternalContact());
        }

        if ($send_internal_cc) {
            $sendtocc = $conf->global->TICKET_NOTIFICATION_EMAIL_FROM;
        }

        $from = $conf->global->TICKET_NOTIFICATION_EMAIL_FROM;
        if (is_array($array_receiver) && count($array_receiver) > 0) {
            foreach ($array_receiver as $key => $receiver) {
                // Create form object
                include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
                $formmail = new FormMail($this->db);

                $attachedfiles = $formmail->get_attached_files();
                $filepath = $attachedfiles['paths'];
                $filename = $attachedfiles['names'];
                $mimetype = $attachedfiles['mimes'];

                $message_to_send = dol_nl2br($message);

                // Envoi du mail
                if (!empty($conf->global->TICKET_DISABLE_MAIL_AUTOCOPY_TO)) {
                    $old_MAIN_MAIL_AUTOCOPY_TO = $conf->global->MAIN_MAIL_AUTOCOPY_TO;
                    $conf->global->MAIN_MAIL_AUTOCOPY_TO = '';
                }
                include_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
                $mailfile = new CMailFile($subject, $receiver, $from, $message_to_send, $filepath, $mimetype, $filename, $sendtocc, '', $deliveryreceipt, -1);
                if ($mailfile->error) {
                    setEventMessages($mailfile->error, null, 'errors');
                } else {
                    $result = $mailfile->sendfile();
                    if ($result) {
                        setEventMessages($langs->trans('MailSuccessfulySent', $mailfile->getValidAddress($from, 2), $mailfile->getValidAddress($receiver, 2)), null, 'mesgs');
                    } else {
                        $langs->load("other");
                        if ($mailfile->error) {
                            setEventMessages($langs->trans('ErrorFailedToSendMail', $from, $receiver), null, 'errors');
                            dol_syslog($langs->trans('ErrorFailedToSendMail', $from, $receiver) . ' : ' . $mailfile->error);
                        } else {
                            setEventMessages('No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS', null, 'errors');
                        }
                    }
                }
                if (!empty($conf->global->TICKET_DISABLE_MAIL_AUTOCOPY_TO)) {
                    $conf->global->MAIN_MAIL_AUTOCOPY_TO = $old_MAIN_MAIL_AUTOCOPY_TO;
                }
            }
        } else {
            $langs->load("other");
            setEventMessages($langs->trans('ErrorMailRecipientIsEmptyForSendTicketMessage'), null, 'warnings');
        }
    }

    /**
     * Print html navbar with link to set ticket status
     *
     * @param	Ticket	$object		Ticket sup
     * @return	void
     */
    public function viewStatusActions(Ticket $object)
    {
        global $langs;

        print '<div class="div-table-responsive-no-min">';
        print '<div class="tagtable centpercent">';
        print '<div class="tagtr liste_titre">';
        print '<div class="tagtd">';
        print '<strong>' . $langs->trans('TicketChangeStatus') . '</strong>';
        print '</div>';
        // Exclude status which requires specific method
        $exclude_status = array(Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELED);
        // Exclude actual status
        $exclude_status = array_merge($exclude_status, array(intval($object->fk_statut)));

        // Sort results to be similar to status object list
        //sort($exclude_status);

        //print '<br><div>';
        foreach ($object->statuts_short as $status => $statut_label) {
            if (!in_array($status, $exclude_status)) {
                print '<div class="tagtd">';

                if ($status == 1)
                {
                    $urlforbutton = $_SERVER['PHP_SELF'] . '?track_id=' . $object->track_id . '&action=mark_ticket_read';	// To set as read, we use a dedicated action
                }
                else
                {
                    $urlforbutton = $_SERVER['PHP_SELF'] . '?track_id=' . $object->track_id . '&action=set_status&new_status=' . $status;
                }

                print '<a class="button buttonticket" href="' . $urlforbutton . '">';
                print img_picto($langs->trans($object->statuts_short[$status]), 'statut' . $status . '.png@ticket') . ' ' . $langs->trans($object->statuts_short[$status]);
                print '</a>';
                print '</div>';
            }
        }
        print '</div></div></div><br>';
    }


      /**
       * deleteObjectLinked
       *
       * @return number
       */
    public function deleteObjectLinked()
    {
        return $this->dao->deleteObjectLinked();
    }

    /**
     * Hook to add email element template
     *
     * @param array 		$parameters   Parameters
     * @param Ticket		$object       Object for action
     * @param string 		$action       Action string
     * @param HookManager 	$hookmanager  Hookmanager object
     * @return int
     */
    public function emailElementlist($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        $error = 0;

        if (in_array('admin', explode(':', $parameters['context']))) {
            $this->results = array('ticket_send' => $langs->trans('MailToSendTicketMessage'));
        }

        if (! $error) {
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }
}
