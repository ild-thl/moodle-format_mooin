import Modal from 'core/modal';
import ModalFactory from 'core/modal_factory';
import ModalRegistry from 'core/modal_registry';

export default class Mooin4Modal extends Modal {
    static TYPE = "format_mooin4/mooin4Modal";
    static TEMPLATE = "format_mooin4/local/content/modals/mooin4Modal";
}
let registered = false;
if (!registered) {
    ModalRegistry.register(Mooin4Modal.TYPE, Mooin4Modal, Mooin4Modal.TEMPLATE);
    registered = true;
}