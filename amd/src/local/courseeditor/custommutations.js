import ajax from 'core/ajax';

export default class {
    

    async completeSection(stateManager, target) {
        const course = stateManager.get('course');
        let ids = [];
        ids.push(target.dataset.id);
        const args = {
            action: 'complete_section',
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

    async updateSectionprogress(stateManager, sectionId, contentid, score, maxscore) {

        await ajax.call([
            {
              methodname: "format_mooin4_setgrade",
              args: { contentid: contentid, score: score, maxscore: maxscore },
            },
          ])[0];  


        const course = stateManager.get('course');
        let ids = [];
        ids.push(sectionId);
        const args = {
            action: 'update_sectionprogress',
            courseid: course.id,
            ids: ids,
            targetsectionid: sectionId,
        };
        let updates = await ajax.call([{
            methodname: 'core_courseformat_update_course',
            args
        }])[0];
        window.console.log("MUTAtION PROGRESS");
        stateManager.processUpdates(JSON.parse(updates));
    }

    async sectionSetChapter(stateManager, target) {
        const course = stateManager.get('course');
        let ids = [];
        const targetSection = stateManager.state.section.get(target.dataset.id);
        ids.push(target.dataset.id);
        // stateManager.state.section.forEach(section => {
        //     if (section.number >= targetSection.number) {
        //         ids.push(section.id);
        //     }
        // });
        
        
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
        stateManager.setReadOnly(false);

        let lastChapter = stateManager.state.section.get(target.dataset.id);
        let invisbleCounter = 0;

        stateManager.state.section.forEach(section => {
            if (section.number > targetSection.number) {
                //statesection = state.section.get(section.id)
                if (section.isChapter) {
                    lastChapter = section;  // Das aktuelle Chapter zwischenspeichern
                    section.isChapter++;
                    invisbleCounter = 0;
                } else if (section.isChapter == false) {
                    
                        section.parentChapter = lastChapter.isChapter;
                        section.innerChapterNumber = section.number - lastChapter.number - invisbleCounter;
                        if (section.visible) {
                            section.prefix = section.parentChapter + "." + section.innerChapterNumber;
                        } else {
                            invisbleCounter++;
                        }
                }
            }
        });
        stateManager.setReadOnly(true);
    }
    async sectionUnsetChapter(stateManager, target) {
        const course = stateManager.get('course');
        let ids = [];
        const targetSection = stateManager.state.section.get(target.dataset.id);
        ids.push(target.dataset.id);
        // const targetSection = stateManager.state.section.get(target.dataset.id);
        // stateManager.state.section.forEach(section => {
        //     if (section.number >= targetSection.number) {
        //         ids.push(section.id);
        //     }
        // });
        
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
        

        stateManager.setReadOnly(false);

        let lastChapter = stateManager.state.section.get(target.dataset.id);
        let prevChapter = lastChapter.isChapter -1;

        stateManager.state.section.forEach(section => {
            window.console.log(prevChapter);
            window.console.log(section.isChapter);
            if (section.isChapter == prevChapter) {
                lastChapter = section;
            }
        });

        let invisbleCounter = 0;

        stateManager.state.section.forEach(section => {
            if (section.number > targetSection.number) {
                //statesection = state.section.get(section.id)
                if (section.isChapter && section.isChapter != 1) {
                    lastChapter = section;
                    section.isChapter--;
                    invisbleCounter = 0;
                } else if (section.isChapter == false) {
                    
                        section.parentChapter = lastChapter.isChapter;
                        section.innerChapterNumber = section.number - lastChapter.number - invisbleCounter;
                        if (section.visible) {
                            section.prefix = section.parentChapter + "." + section.innerChapterNumber;
                        } else {
                            invisbleCounter++;
                        } 
                }
            }
        });
        stateManager.setReadOnly(true);
        stateManager.processUpdates(JSON.parse(updates));
        
    }

    async setLastSectionModal(stateManager, id) {
        const course = stateManager.get('course');
        let ids = [];
        ids.push(id);
        const args = {
            action: 'set_last_section_modal',
            courseid: course.id,
            ids: ids,
            targetsectionid: id,
        };
        let updates = await ajax.call([{
            methodname: 'core_courseformat_update_course',
            args,
        }])[0];
        stateManager.processUpdates(JSON.parse(updates));
    }

    setContinueSection(stateManager, type, id) {
        stateManager.setReadOnly(false);
        const state = stateManager.state;
        const course = state.course;
        course.continueSection = id;
        state.section.forEach((section) => {
            //section.containsActiveSection = false;
            //section.isActiveSection = false;
            if (section.id == id) {
                section.isActiveSection = true;
            }
            
            if (section.parentChapter == state.section.get(id).parentChapter) {
                section.containsActiveSection = true;
                
            }
        });
        state.section.get(id).isActiveSection = true;
        
        stateManager.setReadOnly(true);
    }

    async getContinueSection(stateManager, target) {
        const state = stateManager.state;
        const course = state.course;
        const args = {
            action: 'getContinuesection',
            courseid: course.id,
        };
        let updates = await ajax.call([{
            methodname: 'core_courseformat_update_course',
            args,
        }])[0];
        stateManager.processUpdates(JSON.parse(updates));
    }

    async readAllForumDiscussions(stateManager, forumid) {
        const state = stateManager.state;
        const course = state.course;
        let ids = [];
        ids.push(forumid);
        const args = {
            action: 'readAllForumDiscussions',
            courseid: course.id,
            ids: ids,
        };
        let updates = await ajax.call([{
            methodname: 'core_courseformat_update_course',
            args,
        }])[0];
        stateManager.processUpdates(JSON.parse(updates));
    }

    async reloadAllSectionPrefixes(stateManager, target) {
        const state = stateManager.state;
        const course = state.course;
        let ids = [];
        stateManager.state.section.forEach(section => {
            // if (section.number >= target.number) {
                ids.push(section.id);
            // }
        });
        //ids.push(target); 
        const args = {
            action: 'reload_all_section_prefixes',
            courseid: course.id,
            ids: ids,
        };
        let updates = await ajax.call([{
            methodname: 'core_courseformat_update_course',
            args,
        }])[0];
        stateManager.processUpdates(JSON.parse(updates));
    }
}