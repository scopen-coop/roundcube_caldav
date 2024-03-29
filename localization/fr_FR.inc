<?php

/**
 * +-----------------------------------------------------------------------+
 * | Localization file of the Roundcube Webmail Enigma plugin              |
 * |                                                                       |
 * | Copyright (C) The Roundcube Dev Team                                  |
 * |                                                                       |
 * | Licensed under the GNU General Public License version 3 or            |
 * | any later version with exceptions for skins & plugins.                |
 * | See the README file for a full license statement.                     |
 * +-----------------------------------------------------------------------+
 */

$labels = array();
$labels['server_caldav'] = 'Configuration serveur CalDAV';
$labels['url_base'] = "Indiquez l'url de votre serveur calDAV sous la forme : \n https://my_caldav_server/remote.php/calendars/my_name";
$labels['login'] = 'Indiquer votre login utilisé sur le serveur';
$labels['password'] = 'Indiquez votre mot de passe';
$labels['save_error_msg'] = 'Vos informations sont incorrectes et/ou incomplètes';
$labels['main_calendar'] = 'Veuillez indiquer votre calendrier par default';
$labels['calendar_choice'] = 'Attention : si vous modifiez votre url / login / mot de passe vos préférences seront oubliées. \n
 Veuillez choisir votre calendrier par default  ainsi que les calendriers que vous serez amené à utilisé ';
$labels['connect_error_msg'] = 'La connection avec votre server CalDAV à échouée';
$labels['find_calendar_error'] = 'La récupération des calendriers disponibles a provoqué une erreur';
$labels['something_happened_while_sending_information_to_client'] = "Quelque chose ne s'est pas bien passé lors de l'envoi des informations à afficher ";
$labels['ErrorEmptyArrayOfCalendar'] = "Vous n'avez aucun calendrier sélectionné";
$labels['invitation'] = 'Vous avez reçu une invitation :';
$labels['same_hour_meeting'] = 'Vous avez deja un ou plusieurs rendez vous durant cette période :';
$labels['attendee'] = 'Participants: ';
$labels['reply_all'] = 'Répondre à tous';
$labels['previous_meeting'] = ' Rendez vous précédent: ';
$labels['next_meeting'] = 'Rendez vous suivant: ';
$labels['all_day'] = 'Toute la journée';
$labels['successfully_saved'] = 'Cet événement à été ajouté à votre agenda';
$labels['successfully_sent'] = "Le mail contenant vos modifications a bien été envoyé";
$labels['something_happened'] = "L'enregistrement de cet événement ne s'est pas passé comme prévu";
$labels['something_happened_when_sending'] = "L'envoi d'un mail de modifications a échoué";
$labels['no_participant_to_answer'] = "Aucun mail n'a été envoyé car les participants ne souhaitent pas recevoir de réponse";
$labels['confirm'] = 'Confirmer';
$labels['tentative'] = 'Confirmer Provisoirement';
$labels['decline'] = "Décliner l'invitation";
$labels['confirmed'] = 'Confirmé';
$labels['tentatived'] = 'Confirmé Provisoirement';
$labels['declined'] = "Invitation déclinée";
$labels['confirmed_organizer'] = 'Proposition validée';
$labels['declined_organizer'] = "Proposition refusée";
$labels['location'] = "Lieu: ";
$labels['description'] = "Description: ";
$labels['choose_calendar_to_add'] = "Evénement à ajouter au calendrier: ";
$labels['event_not_found_which_calendar_to_add'] = "Nous n'avons pas trouvé cet événement dans votre calendrier bien que <br/>
vous en soyez l'organisateur, à quel calendrier voulez vous l'ajouter?";
$labels['repeated_event'] = "L'événement se répète à plusieurs occurrences: ";
$labels['use_this_calendar'] = "Utiliser le calendrier ";
$labels['make_this_calendar_default1'] = "Sélectionner le calendrier ";
$labels['make_this_calendar_default2'] = " par défaut";
$labels['reschedule_meeting']= "Reprogrammer cet événement";
$labels['ask_for_reschedule'] = "Demander une reprogrammation";
$labels['ask_rescheduled_msg'] = 'Vous avez demandé à reprogrammer cet événement :';
$labels['cancel'] = "Annuler";
$labels['new_date_start'] = 'Nouvelle date de début pour cet événement';
$labels['new_time_start'] = 'Horaire du début';
$labels['new_time_end'] = 'Horaire de fin';
$labels['new_date_end'] = 'Nouvelle date de fin pour cet événement';
$labels['new_location'] = 'Choisir un nouveau lieu pour cet événement';
$labels['if_rescheduled_msg'] = 'Vous avez reprogrammé cet événement :';
$labels['error_date_inf'] = 'Attention vous ne pouvez pas mettre une date de fin supérieure à la date de début';
$labels['error_incomplete_field'] = "Attention vous n'avez pas remplis les champs nécessaires (soit le champs lieu soit tous les champs date) ";
$labels['modified_event']= "<b>Attention !</b> l'événement affiché n'est pas celui que vous avez reçu, il s'agit d'une version plus récente trouvée sur le calendrier ";
$labels['missing_summary'] = "Sans titre";
$labels['CONFIRMED'] = '[CONFIRMÉ]: ';
$labels['TENTATIVE'] = '[CONF.PROV.]: ';
$labels['CANCELLED'] = "[DÉCLINÉ]: ";
$labels['MODIFIED'] = '[MODIFIÉ]: ';
$labels['CANCEL'] = '[ANNULÉ]: ';
$labels['DECLINECOUNTER'] = '[MODIF REFUSÉE]: ';
$labels['all_day'] = 'Journée';
$labels['loading'] = 'Chargement...';
$labels['something_happened_while_getting_events'] = "La récupération des événements sur le serveur caldav a rencontrée une erreur";
$labels['invitation_modification'] = "Vous avez reçu un proposition de modification pour cet événement";
$labels['invitation_modification_already_studied'] = "La proposition de modification pour cet événement a déjà été étudiée";
$labels['confirm_modification'] = "Confirmer les modifications";
$labels['decline_modification'] = "Decliner les modifications";
$labels['cancel_event'] = "Annuler l'événement";
$labels['cancel_instance'] = "Annuler cette instance uniquement";
$labels['cancelled_instance'] = "Instance Annulée";
$labels['update_event'] = "Mettre à jour l'événement sur votre serveur";
$labels['updated_event'] = "L'événement à été mis à jour";
$labels['cancel_event_on_server'] = "Annuler l'événement sur votre serveur";
$labels['cancelled_event_on_server'] = "L'événement à été annulé sur votre serveur";
$labels['cancelled_event'] = "L'événement a été annulé";
$labels['invitation_accepted'] = " a accepté votre invitation à: ";
$labels['invitation_tentative'] = " est intéressé par votre invitation à: ";
$labels['invitation_declined'] = " a décliné votre invitation à: ";
$labels['unknown_reply'] = " a répondu à votre invitation mais ne fait pas partie des participants";
$labels['new_location_proposal'] = "Emplacement proposé: ";
$labels['new_description_proposal'] = "Description proposée: ";
$labels['new_location_modification'] = "Emplacement modifié: ";
$labels['new_description_modification'] = "Description modifiée: ";
$labels['if_modification'] = "Les modifications sont indiquées en vert";
$labels['if_no_modification'] = "Les modifications ont déjà été prises en compte";
$labels['invitation_send'] = "Vous avez envoyé une invitation pour l'événement: ";
$labels['invitation_decline_modifications1'] = "Vous avez refusé les propositions de changements de ";
$labels['invitation_decline_modifications2'] = " ";
$labels['invitation_declined_by_organizer'] = " Votre proposition de modification n'a pas été acceptée par l'organisateur ";
$labels['invitation_cancel'] = "Cet événement a été supprimé par son organisateur";
$labels['invitation_cancel_for_organizer'] = "Vous avez supprimé cet événement";
$labels['present_but_older'] = 'Vous possédez déjà cet événement sur votre serveur mais sous une version plus ancienne';
$labels['send_comment_with_invitation'] = "Envoyez un commentaire avec votre réponse par mail";
$labels['comment_to_send'] = "Commentaire à envoyer";
$labels['send_without_comment'] = "Envoyer sans commentaire";
$labels['comment'] = "Commentaire de l'expéditeur: ";
$labels['saving_and_sending'] = "Enregistrement de vos modifications et envoi des mails";
$labels['main_calendar_error'] = "Vous devez selectionner un calendrier par défault";
$labels['no_title'] = "Un événement sans titre";
$labels['main_calendar_not_exist'] = "Le calendrier choisis comme principal n'existe pas ou plus sur votre serveur";
$labels['no_calendar_found'] = "Aucun Calendrier n'est disponible sur votre server";
$labels['invitation_without_event_inside'] = "Vous avez reçu une invitation sans événement";


?>
