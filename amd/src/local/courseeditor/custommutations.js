import ajax from 'core/ajax';
export default class {
    async sectionSetChapter(stateManager, target) {
        const course = stateManager.get('course');
        let ids = [];
        const targetSection = stateManager.state.section.get(target.dataset.id);
        stateManager.state.section.forEach(section => {
            if (section.number >= targetSection.number) {
                ids.push(section.id);
            }
        });
        const args = {
            action: 'section_setChapter',
            courseid: course.id,
            ids: ids,
            targetsectionid: target.dataset.id,
        };
        let updates = await ajax.call([{
            methodname: 'core_courseformat_update_course',
            args,
        }])[0];
        stateManager.processUpdates(JSON.parse(updates));
    }
    async sectionUnsetChapter(stateManager, target) {
        const course = stateManager.get('course');
        let ids = [];
        const targetSection = stateManager.state.section.get(target.dataset.id);
        stateManager.state.section.forEach(section => {
            if (section.number >= targetSection.number) {
                ids.push(section.id);
            }
        });
        const args = {
            action: 'section_unsetChapter',
            courseid: course.id,
            ids: ids,
            targetsectionid: target.dataset.id,
        };
        let updates = await ajax.call([{
            methodname: 'core_courseformat_update_course',
            args,
        }])[0];
        stateManager.processUpdates(JSON.parse(updates));
    }
}