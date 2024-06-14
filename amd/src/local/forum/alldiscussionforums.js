import {BaseComponent} from 'core/reactive';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import CustomMutations from '../courseeditor/custommutations';

export default class Component extends BaseComponent {

    create() {
        this.name = 'alldiscussionforums';
       
        this.selectors = {
            UNREADLINK: `[data-for='unread_link']`, 
            UNREADCONTAINER: `[data-for='mark_as_read_container']`,
        };
    }

    static init(target, selectors) {
        return new Component({
            element: document.getElementById(target),
            reactive: getCurrentCourseEditor(),
            selectors,
        });
    }

    stateReady(state) {
        const unreadlinks = this.getElements(this.selectors.UNREADLINK);
        unreadlinks.forEach(link => {
            link.addEventListener('click', this._unreadForum.bind(this));
        });
        window.console.log(unreadlinks);
        const mutations = new CustomMutations();
        this.reactive.addMutations({
            readAllForumDiscussions: mutations.readAllForumDiscussions
          });
    }

    _unreadForum(event) {
        const unreadLink = event.currentTarget;
        const dataId = unreadLink.getAttribute('data-id');
        this.reactive.dispatch("readAllForumDiscussions", dataId);

        const unreadContainer = unreadLink.closest(this.selectors.UNREADCONTAINER);
        if (unreadContainer) {
            unreadContainer.remove();
        }
    }
}