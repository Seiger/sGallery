// Exercise the actual settings-page handler without a server or persistent writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const blade = fs.readFileSync(path.join(__dirname, '../views/settings.blade.php'), 'utf8');
assert(!blade.includes('id="sg-save"'), 'No separate gallery save button');
let script = blade.slice(blade.lastIndexOf('<script>') + 8, blade.lastIndexOf('</script>'))
    .replace(/const token = .*?;\r?\n/, "const token='test',csrf='test',templates=[],initial=[];\n")
    .replace('@json($defaults)', '{}')
    .replace('@json($messages)', JSON.stringify({delete:'Delete',saveError:'Save failed',saved:'Saved',cacheCleared:'Removed: :count'}))
    .replace('@json(EVO_SITE_URL)', "'https://example.test/'")
    .replace(/@json\(route\('([^']+)'\)\)/g, (_, name) => JSON.stringify(name));
const fields = [
    {dataset:{sg:'quality'},type:'number',value:'100'},
    {dataset:{sg:'watermark.file'},type:'text',value:''},
];
let loaded, submit, requests=0, generalSaves=0, reject=false, selection;
const status = {textContent:''};
const rows = [{querySelector: selector => ({value:selector==='select'?'1':'slider'})}];
const elements = {
    'sgallery-settings': {addEventListener(){},querySelectorAll:selector=>selector==='[data-sg]'?fields:rows},
    settingsPane:{appendChild(){}}, 'sg-status':status, 'sg-add':{},
    'sg-watermark':{value:'',addEventListener(){}}, 'sg-preview':{style:{}},
    'sg-clear-cache':{}, 'sg-defaults':{},
};
const context = {
    document:{getElementById:id=>elements[id],addEventListener:(name,cb)=>{loaded=cb;},settings:{addEventListener:(name,cb)=>{submit=cb;}}},
    tpSettings:{pages:[{},{}],addTabPage(){},setSelectedIndex:index=>{selection=index;}},
    actions:{save(){generalSaves++;}}, documentDirty:false, URL,
    FormData:class extends Map {append(key,value){this.set(key,value);}},
    async fetch(){requests++;return {ok:!reject,json:async()=>reject?{error:'Synthetic save failure'}:{success:true}};},
};
vm.runInNewContext(script, context);
loaded();
(async()=>{
    await context.actions.save();
    assert.equal(requests,0,'Unchanged gallery must not be submitted');
    assert.equal(generalSaves,1,'General settings still save');
    fields[0].value='90';
    await context.actions.save();
    assert.equal(requests,1,'Actual gallery change is submitted');
    fields[0].value='75';fields[0].value='90';
    await context.actions.save();
    assert.equal(requests,1,'Reverted edit does not cause another gallery write');
    rows[0].querySelector=selector=>({value:selector==='select'?'1':'slider, footer'});
    await context.actions.save();
    assert.equal(requests,2,'Template block changes are included');
    reject=true;fields[1].value='assets/missing.png';
    const before=generalSaves;
    await context.actions.save();
    assert.equal(generalSaves,before,'Gallery validation failure stops general submission');
    assert.equal(status.textContent,'Synthetic save failure');
    assert.equal(selection,1,'Failure selects the gallery tab');
    reject=false;
    await submit({defaultPrevented:false,preventDefault(){}});
    assert.equal(generalSaves,before+1,'Form submission supports retry after correcting an error');
    const prior=requests;
    fields[0].value='80';
    await Promise.all([context.actions.save(),context.actions.save()]);
    assert.equal(requests,prior+1,'Double click creates only one gallery request');
    console.log('sGallery conditional general save OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
