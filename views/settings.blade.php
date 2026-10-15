@php
    $value = fn($key, $default = '') => \Illuminate\Support\Arr::get($settings, $key, $default);
    $messages = ['delete' => __('sGallery::settings.delete'), 'saveError' => __('sGallery::settings.save_error'), 'saved' => __('sGallery::settings.saved'), 'cacheCleared' => __('sGallery::settings.cache_cleared')];
    $assignments = [];
    foreach (\Seiger\sGallery\Services\GallerySettings::assignments($settings) as $id => $blocks) {
        $assignments[] = ['id' => $id, 'blocks' => implode(', ', $blocks)];
    }
@endphp
<div class="tab-page" id="sgallery-settings">
    <h2 class="tab">sGallery</h2>
<style>
#sgallery-settings {--sg-card:#fff;--sg-border:#dde3ea;--sg-text:#344054;--sg-muted:#475467;--sg-accent:#036efe;}
#sgallery-settings .sg-content {padding:18px 16px;max-width:none;}
#sgallery-settings .sg-heading {margin:0 0 6px;font-size:20px;font-weight:400;color:var(--sg-text);}
#sgallery-settings .sg-intro {margin:0 0 18px;color:var(--sg-muted);line-height:1.45;}
#sgallery-settings .sg-layout {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;align-items:start;margin-bottom:28px;}
#sgallery-settings .sg-layout > .sg-card:only-child {grid-column:1 / -1;}
#sgallery-settings .sg-card {min-width:0;padding:18px;border:1px solid var(--sg-border);background:var(--sg-card);box-shadow:0 4px 14px rgba(38,50,56,.05);}
#sgallery-settings .sg-card-title {display:flex;align-items:center;gap:8px;margin:0 0 16px;color:var(--sg-text);font-size:16px;font-weight:700;}
#sgallery-settings .sg-icon {width:20px;height:20px;flex:0 0 20px;color:var(--sg-accent);}
#sgallery-settings .form-group {margin:0 0 14px;}
#sgallery-settings label {display:block;margin:0 0 6px;color:var(--sg-text);font-weight:700;}
#sgallery-settings .form-control {width:100%;max-width:none;min-width:0;min-height:36px;}
#sgallery-settings small {display:block;margin-top:5px;font-size:12px;line-height:1.45;color:var(--sg-muted);font-weight:400;}
#sgallery-settings .sg-card>label:has(input[type=checkbox]) {display:flex;align-items:center;gap:8px;margin-bottom:0;}
#sgallery-settings input[type=checkbox] {width:16px;height:16px;margin:0;accent-color:var(--sg-accent);}
#sgallery-settings .sg-card p {color:var(--sg-muted);line-height:1.45;}
#sgallery-settings .sg-preview {display:flex;align-items:center;justify-content:center;min-height:140px;margin:16px 0 0;border:1px dashed var(--sg-border);background:linear-gradient(45deg,#f3f4f6 25%,transparent 25%),linear-gradient(-45deg,#f3f4f6 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#f3f4f6 75%),linear-gradient(-45deg,transparent 75%,#f3f4f6 75%);background-size:20px 20px;background-position:0 0,0 10px,10px -10px,-10px 0;}
#sgallery-settings .sg-preview:has(img[style*="display:none"]),#sgallery-settings .sg-preview:has(img[style*="display: none"]) {display:none;}
#sgallery-settings .sg-input-group {display:flex;flex-wrap:nowrap;}
#sgallery-settings .sg-input-group>.form-control {flex:1 1 auto;width:1%;}
#sgallery-settings .input-group-btn {display:flex;flex:0 0 auto;}
#sgallery-settings .input-group-btn .btn {height:100%;min-height:36px;white-space:nowrap;}
#sgallery-settings .table {margin:0 0 14px;width:100%;}
#sgallery-settings .table th {color:var(--sg-text);font-size:13px;border-top:0;}
#sgallery-settings .table td {vertical-align:middle;padding:8px 6px;}
#sgallery-settings .table td:last-child {width:1%;white-space:nowrap;}
#sgallery-settings #sg-status {color:var(--sg-text);margin-top:14px;}
#sgallery-settings #sg-status:empty {display:none;}
@media(max-width:1199px) {#sgallery-settings .sg-layout {grid-template-columns:1fr;}}
</style>
    <div class="sg-content">
@if(\Seiger\sGallery\Services\GallerySettings::hasLegacyAssignments($settings))
<p class="alert alert-warning">{{__('sGallery::settings.legacy_assignments')}}</p>
@endif
<h3 class="sg-heading">{{__('sGallery::settings.general')}}</h3>
<p class="sg-intro">{{__('sGallery::settings.general_help')}}</p>
<div class="sg-layout"><section class="sg-card "><h4 class="sg-card-title">@svg('tabler-photo-edit', 'sg-icon'){{__('sGallery::settings.processing')}}</h4><div class="form-group"><label for="sg-cache-dir">{{__('sGallery::settings.cache_directory')}}</label><div class="input-group sg-input-group"><input class="form-control" id="sg-cache-dir" data-sg="imageCacheDir" value="{{rtrim($value('imageCacheDir','assets/cache/'), '/') . '/'}}"><div class="input-group-btn"><button type="button" class="btn btn-secondary" id="sg-clear-cache">{{__('sGallery::settings.clear_cache')}}</button></div></div></div>
        @foreach(['quality' => [__('sGallery::settings.quality'), 100, 1, 100], 'sharpen' => [__('sGallery::settings.sharpen'), 0, 0, 100]] as $key => $field)
            <div class="form-group"><label for="sg-{{$key}}">{{$field[0]}}</label><input class="form-control" id="sg-{{$key}}" data-sg="{{$key}}" type="number" min="{{$field[2]}}" max="{{$field[3]}}" value="{{$value($key, $field[1])}}"></div>
        @endforeach
        <div class="form-group"><label>{{__('sGallery::settings.format')}}</label><select class="form-control" data-sg="format">@foreach(['avif'=>'AVIF','webp'=>'WebP','original'=>__('sGallery::settings.original')] as $format=>$label)<option value="{{$format}}" @if(($value('format','avif') === 'auto' ? 'avif' : (in_array($value('format'), ['jpg','jpeg','png','gif']) ? 'original' : $value('format','avif'))) === $format) selected @endif>{{$label}}</option>@endforeach</select><small>{{__('sGallery::settings.format_help')}}</small></div>
        <label><input type="checkbox" data-sg="optimize" @if($value('optimize',true)) checked @endif> {{__('sGallery::settings.optimize')}}</label>
</section><section class="sg-card "><h4 class="sg-card-title">@svg('tabler-rubber-stamp', 'sg-icon'){{__('sGallery::settings.watermark')}}</h4><p>{{__('sGallery::settings.watermark_help')}}</p>        <div class="form-group"><label for="sg-watermark">{{__('sGallery::settings.watermark_file')}}</label><div class="input-group sg-input-group"><input class="form-control" id="sg-watermark" data-sg="watermark.file" value="{{$value('watermark.file')}}"><div class="input-group-btn"><input type="button" class="btn btn-secondary" onclick="BrowseServer('sg-watermark')" value="{{__('sGallery::settings.choose')}}" aria-label="{{__('sGallery::settings.choose_watermark')}}"></div></div><small>{{__('sGallery::settings.watermark_file_help')}}</small></div>
        <p class="sg-preview"><img id="sg-preview" alt="{{__('sGallery::settings.watermark_preview')}}" style="max-width:240px;max-height:120px;display:none"></p>
        <div class="form-group"><label>{{__('sGallery::settings.position')}}</label><select class="form-control" data-sg="watermark.position">@foreach(['top-left','top','top-right','left','center','right','bottom-left','bottom','bottom-right'] as $position)<option value="{{$position}}" @if($value('watermark.position','bottom-right') === $position) selected @endif>{{__('sGallery::settings.position_'.$position)}}</option>@endforeach</select></div>
        @foreach(['width'=>[__('sGallery::settings.width'),'20%'], 'padding'=>[__('sGallery::settings.padding'),'3%'], 'opacity'=>[__('sGallery::settings.opacity'),100]] as $key=>$field)
            <div class="form-group"><label>{{$field[0]}}</label><input class="form-control" data-sg="watermark.{{$key}}" value="{{$value('watermark.'.$key,$field[1])}}"></div>
        @endforeach
</section></div>
<h3 class="sg-heading">{{__('sGallery::settings.display')}}</h3>
<p class="sg-intro">{{__('sGallery::settings.display_help')}}</p>
<div class="sg-layout"><section class="sg-card "><h4 class="sg-card-title">@svg('tabler-template', 'sg-icon'){{__('sGallery::settings.assignments')}}</h4><p>{{__('sGallery::settings.assignments_help')}}</p>        <table class="table"><thead><tr><th>{{__('sGallery::settings.template')}}</th><th>{{__('sGallery::settings.blocks')}}</th><th></th></tr></thead><tbody id="sg-assignments"></tbody></table>
        <button type="button" class="btn btn-secondary" id="sg-add">{{__('sGallery::settings.add_assignment')}}</button>
</section></div>
<div id="sg-status" role="status"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('sgallery-settings');
    document.getElementById('settingsPane').appendChild(root);
    tpSettings.addTabPage(root);
    const token = @json($token), csrf = @json(csrf_token()), templates = @json($resourceTemplates->map(fn($t) => ['id'=>$t->id,'name'=>$t->templatename])->values()), initial = @json($assignments);
    const messages = @json($messages);
    const status = document.getElementById('sg-status');
    root.addEventListener('input', () => {documentDirty = true;});
    root.addEventListener('change', () => {documentDirty = true;});
    function row(item = {}) {
        const tr = document.createElement('tr'), select = document.createElement('select'), input = document.createElement('input'), button = document.createElement('button');
        select.className = input.className = 'form-control'; select.dataset.template = '1'; input.dataset.blocks = '1'; input.value = item.blocks || '';
        for (const t of templates) {const option = new Option(t.name + ' (#' + t.id + ')', t.id); option.selected = Number(item.id) === Number(t.id); select.add(option);}
        button.type = 'button'; button.className = 'btn btn-secondary'; button.textContent = messages.delete; button.onclick = () => {tr.remove();documentDirty=true;};
        for (const control of [select,input,button]) {const td=document.createElement('td');td.appendChild(control);tr.appendChild(td);} document.getElementById('sg-assignments').appendChild(tr);
    }
    initial.forEach(row); document.getElementById('sg-add').onclick = () => {row();documentDirty=true;};
    function preview() {const image=document.getElementById('sg-preview'), path=document.getElementById('sg-watermark').value;image.style.display=path?'block':'none';if(path) image.src = new URL(path.replace(/^\//,''), @json(EVO_SITE_URL)).href;}
    preview();document.getElementById('sg-watermark').addEventListener('change',()=>{documentDirty=true;preview();});
    async function send(url, data) {const response=await fetch(url,{method:'POST',body:data,credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf}});const result=await response.json();if(!response.ok || result.error) throw new Error(result.error || messages.saveError);return result;}
    function collectSettings() {
        const settings={templates:[]};
        root.querySelectorAll('[data-sg]').forEach(el=>{let target=settings;const parts=el.dataset.sg.split('.');const key=parts.pop();for(const p of parts)target=target[p]??={};target[key]=el.type==='checkbox'?el.checked:el.value;});
        root.querySelectorAll('#sg-assignments tr').forEach(tr=>settings.templates.push({id:tr.querySelector('select').value,blocks:tr.querySelector('input').value}));
        return settings;
    }
    let savedSnapshot=JSON.stringify(collectSettings()), saving=false;
    async function saveIfChanged() {
        const snapshot=JSON.stringify(collectSettings());
        if(snapshot===savedSnapshot)return;
        const data=new FormData();data.append('token',token);data.append('settings',snapshot);
        await send(@json(route('sGallery.settings-save')),data);
        savedSnapshot=snapshot;
        status.textContent=messages.saved;
    }
    document.getElementById('sg-clear-cache').onclick=async function(){
        this.disabled=true;
        try {const data=new FormData();data.append('token',token);data.append('directory',document.getElementById('sg-cache-dir').value);const result=await send(@json(route('sGallery.settings-cache')),data);status.textContent=messages.cacheCleared.replace(':count', String(result.count));}
        catch(e){status.textContent=e.message;}finally{this.disabled=false;}
    };
    const originalSave=actions.save;
    actions.save=async function(){
        if(saving)return;
        saving=true;
        try {await saveIfChanged();originalSave.call(actions);}
        catch(e){status.textContent=e.message;tpSettings.setSelectedIndex(tpSettings.pages.length-1);}
        finally {saving=false;}
    };
    document.settings.addEventListener('submit',function(event){
        if(event.defaultPrevented)return;
        event.preventDefault();
        return actions.save();
    });
});
</script>
