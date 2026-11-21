'use strict';
const $ = selector => document.querySelector(selector);
const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const labels = {dashboard:'Overview',students:'Students',tasks:'Academic tasks',submissions:'Submissions'};
let state = {page:'dashboard',students:[],tasks:[],submissions:[],dashboard:{},query:'',filter:'all',token:'',editing:null,loading:false};
let renderSequence=0;
async function api(route, method='GET', data=null, parameters={}) {
  const url = new URL('api.php', location.href.split('#')[0]);
  url.searchParams.set('route',route);
  for (const [key,value] of Object.entries(parameters)) url.searchParams.set(key,value);
  const options={method,headers:{'X-CSRF-Token':state.token}};
  if (data!==null) { options.headers['Content-Type']='application/json'; options.body=JSON.stringify(data); }
  const response=await fetch(url,options);
  let body;
  try { body=await response.json(); } catch { throw new Error('The local server returned an unreadable response.'); }
  if (!response.ok) throw new Error(body.error || `Request failed (${response.status}).`);
  return body.data;
}
function notify(message,error=false) {
  $('#notice').textContent=message; $('#notice').className=`notice${error?' error':''}`; $('#notice').hidden=false;
}
function niceDate(value) { return new Date(`${value}T12:00:00`).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}); }
function initials(name) { return name.split(/\s+/).slice(0,2).map(w=>w[0]).join('').toUpperCase(); }
function badge(status) { return `<span class="badge ${status}">${status[0].toUpperCase()+status.slice(1)}</span>`; }
function empty(title,description,action='') { return `<div class="empty"><div class="empty-icon">▤</div><h3>${title}</h3><p>${description}</p>${action}</div>`; }
async function refresh() {
  const sequence=++renderSequence;
  try {
    const [students,tasks,submissions,dashboard]=await Promise.all(['students','tasks','submissions','dashboard'].map(r=>api(r)));
    if (sequence!==renderSequence) return;
    Object.assign(state,{students,tasks,submissions,dashboard});
    render();
  } catch(error) { notify(error.message,true); }
}
function stats() {
  const d=state.dashboard;
  return `<div class="stats">${[
    ['Total students',d.students,'Registered in your workspace','♙'],
    ['Academic tasks',d.tasks,'Shared across all students','▤'],
    ['Submitted',d.submitted,'Completed student-task records','✓'],
    ['Pending submissions',d.pending,`${d.overdue} overdue · needs your attention`,'◷']
  ].map(([name,count,note,icon])=>`<div class="stat"><div class="stat-top">${name}<span class="stat-icon" aria-hidden="true">${icon}</span></div><div class="stat-number">${count}</div><div class="stat-note">${note}</div></div>`).join('')}</div>`;
}
function studentRows(students,compact=false) {
  return students.map(s=>`<tr><td><div class="name-cell"><span class="avatar">${escapeHTML(initials(s.full_name))}</span><div>${escapeHTML(s.full_name)}<small>${escapeHTML(s.student_number)}</small></div></div></td><td>${escapeHTML(s.course)}<small>Year ${s.year_level} · ${escapeHTML(s.section)}</small></td>${compact?'':`<td>${escapeHTML(s.email)}</td><td><div class="row-actions"><button class="row-action" data-edit="students" data-id="${s.id}">Edit<span class="sr-only"> ${escapeHTML(s.full_name)}</span></button><button class="row-action delete" data-delete="students" data-id="${s.id}" aria-label="Delete ${escapeHTML(s.full_name)}">Delete</button></div></td>`}</tr>`).join('');
}
function overview() {
  const tasks=[...state.tasks].sort((a,b)=>a.due_date.localeCompare(b.due_date)).slice(0,4);
  return `${stats()}<div class="dashboard-grid"><div><section class="panel"><div class="panel-heading"><div><h2>Submission progress</h2><p>Every student. Every task. One clear picture.</p></div><span class="badge submitted">This workspace</span></div><div class="progress-panel"><div class="progress-line"><strong>${state.dashboard.completion}%</strong><span>${state.dashboard.submitted} of ${state.dashboard.expected} submissions completed</span></div><progress id="completion-progress" max="100" value="${state.dashboard.completion}">${state.dashboard.completion}%</progress><div class="legend"><span>Submitted</span><span>Pending, including overdue</span></div></div></section><section class="callout"><h3>Small steps, steady progress.</h3><p>Keep student records organized and make every deadline a little easier to manage.</p><a class="button" href="#submissions">Review submissions →</a></section></div><section class="panel"><div class="panel-heading"><div><h2>Task deadlines</h2><p>Keep the next due date in sight.</p></div><a class="text-link" href="#tasks">View all →</a></div>${tasks.length?tasks.map(t=>{const date=new Date(`${t.due_date}T12:00:00`);return `<div class="upcoming-item"><div class="date-tile"><b>${date.getDate()}</b><span>${date.toLocaleDateString('en-PH',{month:'short'})}</span></div><div><h3>${escapeHTML(t.title)}</h3><p>${escapeHTML(t.subject)} · ${niceDate(t.due_date)}</p></div></div>`}).join(''):empty('A fresh start','Add an academic task to see its deadline here.')}</section></div><section class="panel section-spacer"><div class="panel-heading"><div><h2>Recently added students</h2><p>Your student directory, at a glance.</p></div><a class="text-link" href="#students">View directory →</a></div>${state.students.length?`<div class="table-wrap"><table><thead><tr><th>STUDENT</th><th>PROGRAM / SECTION</th></tr></thead><tbody>${studentRows(state.students.slice(0,4),true)}</tbody></table></div>`:empty('Welcome to your workspace','Add your first student, or explore with fictional sample records.',`<button class="button secondary" data-demo>Load demo records</button>`)}</section>`;
}
function toolbar(placeholder,rows) {
  return `<div class="toolbar"><div class="search"><input id="search" type="search" maxlength="200" aria-label="${placeholder}" placeholder="${placeholder}" value="${escapeHTML(state.query)}"></div>${state.page==='submissions'?`<select id="status-filter" aria-label="Filter submission status">${['all','pending','submitted','overdue'].map(f=>`<option value="${f}" ${state.filter===f?'selected':''}>${f==='all'?'All statuses':f==='pending'?'Pending + overdue':f[0].toUpperCase()+f.slice(1)}</option>`).join('')}</select>`:''}<span class="result-count">${rows} record${rows===1?'':'s'}</span></div>`;
}
function matching(record,keys) { return keys.some(k=>String(record[k]).toLocaleLowerCase().includes(state.query.toLocaleLowerCase())); }
function directory() {
  const students=state.students.filter(s=>matching(s,['student_number','full_name','email','course','section']));
  return `<section class="panel"><div class="panel-heading"><div><h2>Student directory</h2><p>A home for every student's information.</p></div></div>${toolbar('Search name, student number, or course…',students.length)}${students.length?`<div class="table-wrap"><table><thead><tr><th>STUDENT</th><th>PROGRAM / SECTION</th><th>EMAIL ADDRESS</th><th>ACTIONS</th></tr></thead><tbody>${studentRows(students)}</tbody></table></div>`:empty('No students found',state.query?'Try another search.':'Use “Add student” to create your first record.')}</section>`;
}
function taskList() {
  const tasks=state.tasks.filter(t=>matching(t,['title','subject','description']));
  return `<section class="panel"><div class="panel-heading"><div><h2>Academic tasks</h2><p>All tasks are assigned to every registered student.</p></div></div>${toolbar('Search tasks or subjects…',tasks.length)}${tasks.length?`<div class="table-wrap"><table><thead><tr><th>TASK</th><th>SUBJECT</th><th>DEADLINE</th><th>COMPLETED</th><th>ACTIONS</th></tr></thead><tbody>${tasks.map(t=>`<tr><td><strong>${escapeHTML(t.title)}</strong><small>${escapeHTML(t.description)}</small></td><td>${escapeHTML(t.subject)}</td><td>${niceDate(t.due_date)}</td><td>${state.submissions.filter(s=>s.task_id===t.id&&s.status==='submitted').length} / ${state.students.length}</td><td><div class="row-actions"><button class="row-action" data-edit="tasks" data-id="${t.id}" aria-label="Edit ${escapeHTML(t.title)}">Edit</button><button class="row-action delete" data-delete="tasks" data-id="${t.id}" aria-label="Delete ${escapeHTML(t.title)}">Delete</button></div></td></tr>`).join('')}</tbody></table></div>`:empty('No tasks found',state.query?'Try another search.':'Add a task with its subject and deadline.')}</section>`;
}
function submissionList() {
  const submissions=state.submissions.filter(s=>matching(s,['full_name','student_number','title','subject'])&&(state.filter==='all'||state.filter===s.status||(state.filter==='pending'&&s.status==='overdue')));
  return `${stats()}<section class="panel"><div class="panel-heading"><div><h2>Submission tracker</h2><p>Pending counts include overdue work. Deadlines end at 11:59 PM, Manila time.</p></div></div>${toolbar('Search student or task…',submissions.length)}${submissions.length?`<div class="table-wrap"><table><thead><tr><th>STUDENT</th><th>ACADEMIC TASK</th><th>DEADLINE</th><th>STATUS</th><th>ACTION</th></tr></thead><tbody>${submissions.map(s=>`<tr><td>${escapeHTML(s.full_name)}<small>${escapeHTML(s.student_number)}</small></td><td>${escapeHTML(s.title)}<small>${escapeHTML(s.subject)}</small></td><td>${niceDate(s.due_date)}${s.submitted_at?`<small>Submitted: ${escapeHTML(s.submitted_at)}</small>`:''}</td><td>${badge(s.status)}</td><td><button class="submission-button" data-submit="${s.status==='submitted'?'pending':'submitted'}" data-student="${s.student_id}" data-task="${s.task_id}" aria-label="${s.status==='submitted'?'Mark pending':'Mark submitted'}: ${escapeHTML(s.full_name)}, ${escapeHTML(s.title)}">${s.status==='submitted'?'Mark pending':'Mark submitted'}</button></td></tr>`).join('')}</tbody></table></div>`:empty('No submissions to show',state.query||state.filter!=='all'?'Try a different search or status filter.':'Add at least one student and one academic task to start tracking.')}</section>`;
}
function render() {
  const names={dashboard:['Your academic overview','A clear view of your students, tasks, and what comes next.'],students:['A place for every student','Manage student information and keep your records up to date.'],tasks:['Plan the work ahead','Organize academic activities, subjects, and deadlines.'],submissions:['See every step forward','Track submitted, pending, and overdue academic work.']};
  $('#breadcrumb').textContent=labels[state.page]; $('#page-title').textContent=names[state.page][0]; $('#page-description').textContent=names[state.page][1];
  $('#nav-students').textContent=state.dashboard.students;
  document.querySelectorAll('nav a').forEach(a=>{a.classList.toggle('active',a.dataset.page===state.page);if(a.dataset.page===state.page)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current')});
  $('#primary-action').textContent=state.page==='tasks'?'+ Add task':state.page==='submissions'?'↻ Refresh records':'+ Add student';
  $('#view').innerHTML=({dashboard:overview,students:directory,tasks:taskList,submissions:submissionList}[state.page])();
  const search=$('#search');
  if(search) search.addEventListener('input',event=>{state.query=event.target.value;const start=event.target.selectionStart;render();$('#search').focus();$('#search').setSelectionRange(start,start)});
  $('#status-filter')?.addEventListener('change',event=>{state.filter=event.target.value;render()});
}
const studentFields=[['student_number','Student number','text',30],['full_name','Full name','text',100],['email','Email address','email',150],['course','Program / course','text',80],['year_level','Year level','select'],['section','Section','text',30]];
const taskFields=[['title','Task title','text',120],['subject','Subject / course code','text',80],['due_date','Deadline','date'],['description','Description (optional)','textarea',2000]];
function openEditor(type,id=null) {
  const record=id?state[type].find(r=>r.id===id):{};
  state.editing={type,id};
  $('#editor-title').textContent=`${id?'Edit':'Add'} ${type==='students'?'student':'academic task'}`;
  $('#form-error').textContent='';
  $('#form-fields').innerHTML=`<div class="form-grid">${(type==='students'?studentFields:taskFields).map(([name,label,field,max])=>`<label class="field${field==='textarea'?' wide':''}">${label}${field==='select'?`<select name="${name}">${[1,2,3,4,5,6].map(y=>`<option value="${y}" ${Number(record[name]||1)===y?'selected':''}>Year ${y}</option>`).join('')}</select>`:field==='textarea'?`<textarea name="${name}" maxlength="${max}">${escapeHTML(record[name])}</textarea>`:`<input name="${name}" type="${field}" ${max?`maxlength="${max}"`:''} value="${escapeHTML(record[name])}" required>`}</label>`).join('')}</div>`;
  $('#editor').showModal();
}
async function confirmDelete(type,id) {
  const record=state[type].find(r=>r.id===id);
  $('#confirm-title').textContent=`Delete ${type==='students'?'student':'task'}?`;
  $('#confirm-message').textContent=`“${record.full_name||record.title}” and its related submission records will be permanently removed.`;
  $('#confirmation').showModal();
  $('#confirm-yes').onclick=async()=>{
    $('#confirm-yes').disabled=true;
    try { await api(type,'DELETE',null,{id});$('#confirmation').close();notify('Record deleted.');await refresh(); }
    catch(error){$('#confirmation').close();notify(error.message,true)}
    finally{$('#confirm-yes').disabled=false}
  };
}
$('#primary-action').onclick=()=>state.page==='submissions'?refresh():openEditor(state.page==='tasks'?'tasks':'students');
$('#close-editor').onclick=$('#cancel-editor').onclick=()=>$('#editor').close();
$('#confirm-cancel').onclick=()=>$('#confirmation').close();
$('#record-form').addEventListener('submit',async event=>{
  event.preventDefault();$('#save-record').disabled=true;$('#form-error').textContent='';
  try {
    const data=Object.fromEntries(new FormData(event.target));
    if('year_level' in data)data.year_level=Number(data.year_level);
    const {type,id}=state.editing;
    await api(type,id?'PUT':'POST',data,id?{id}:{});
    $('#editor').close();notify(`Record ${id?'updated':'added'} successfully.`);await refresh();
  }catch(error){$('#form-error').textContent=error.message}
  finally{$('#save-record').disabled=false}
});
$('#view').addEventListener('click',async event=>{
  const button=event.target.closest('button');if(!button)return;
  if(button.dataset.edit){openEditor(button.dataset.edit,Number(button.dataset.id));return}
  if(button.dataset.delete){confirmDelete(button.dataset.delete,Number(button.dataset.id));return}
  if(button.hasAttribute('data-demo')) {
    button.disabled=true;
    try{await api('demo','POST',{});notify('Fictional demo records loaded. You can edit or delete them.');await refresh()}
    catch(error){notify(error.message,true);button.disabled=false}
  }
  if(button.dataset.submit){
    button.disabled=true;
    try{await api('submissions','PUT',{student_id:Number(button.dataset.student),task_id:Number(button.dataset.task),status:button.dataset.submit});notify('Submission status updated.');await refresh()}
    catch(error){notify(error.message,true);button.disabled=false}
  }
});
function navigate(){const page=location.hash.slice(1);state.page=labels[page]?page:'dashboard';state.query='';state.filter='all';render()}
window.addEventListener('hashchange',navigate);
$('#today').textContent=new Date().toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric',timeZone:'Asia/Manila'});
(async()=>{try{state.token=(await api('session')).csrf_token;await refresh();navigate()}catch(error){notify(`Cannot connect to the local server. ${error.message}`,true)}})();
