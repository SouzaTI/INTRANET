<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 min-w-0 min-h-0 overflow-hidden bg-slate-100">
    <div class="w-full h-full min-h-0 flex flex-col px-4 sm:px-5 lg:px-6 py-4">

        <div class="mb-3 shrink-0 flex items-center justify-between gap-4 flex-wrap">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-600">Governança</p>
                <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Governança</h1>
                <p class="mt-1 text-xs text-slate-500">
                    Estrutura, pessoas, responsabilidades e relacionamentos.
                </p>
            </div>

            <div class="px-3 py-2 rounded-xl bg-white border border-slate-200 shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                        Acesso de consulta
                    </span>
                </div>
            </div>
        </div>

        <section id="governanca-app" class="flex-1 min-h-0">
<div class="toolbar">
  <button class="active" id="btnTree" onclick="Gov.setView('tree')">Estrutura</button>
  <button id="btnPeople" onclick="Gov.setView('people')">Pessoas</button>
  <button onclick="Gov.expandAll()">Expandir tudo</button>
  <button onclick="Gov.collapseAll()">Recolher</button>
  <button id="btnFilters" onclick="Gov.toggleFilters()">Exibir / ocultar</button>
  <button onclick="Gov.reloadWorkbook()">Atualizar dados</button>
  <input id="search" class="search" placeholder="Pesquisar estrutura, pessoa, processo ou atividade..." oninput="Gov.runSearch()">
</div>

<div id="filebox" class="filebox">
  <b>Não foi possível carregar os dados da Governança.</b>
  <span id="fileboxMessage">Verifique o arquivo da matriz no servidor ou o endpoint da Governança.</span>
</div>

<div class="statusbar">
  <span id="loadStatus" class="status warn">Carregando...</span>
  <span>Fonte de dados protegida pelo servidor da intranet.</span>
  <span id="lastLoad"></span>
</div>

<div class="stats">
  <div class="stat"><strong id="sResp">-</strong><span>responsabilidades</span></div>
  <div class="stat"><strong id="sPeople">-</strong><span>pessoas</span></div>
  <div class="stat"><strong id="sStruct">-</strong><span>estruturas</span></div>
  <div class="stat"><strong id="sMain">-</strong><span>papéis principais</span></div>
  <div class="stat"><strong id="sSupport">-</strong><span>apoios / backups</span></div>
</div>

<div id="filters" class="filters">
  <h3>Escolha os ramos que deseja visualizar</h3>
  <div id="filterGrid" class="filter-grid"></div>
  <div style="margin-top:10px;display:flex;gap:8px">
    <button onclick="Gov.showAll()">Mostrar tudo</button>
    <button onclick="Gov.hideEmptyToggle()">Ocultar ramos vazios</button>
  </div>
</div>

<div class="layout">
  <main class="card">
    <div class="head">
      <div><h2 id="treeTitle">Estrutura oficial</h2><p id="treeSub">Hierarquia lida diretamente do Excel.</p></div>
      <div class="legend">
        <span class="badge b-main">Principal</span>
        <span class="badge b-support">Apoio</span>
        <span class="badge b-backup">Backup</span>
      </div>
    </div>
    <div id="treeArea" class="treewrap"><div class="empty">Lendo o Excel...</div></div>
  </main>

  <aside class="card">
    <div id="detail" class="detail">
      <h3>Detalhes</h3>
      <div class="sub">Clique em uma estrutura, pessoa ou processo.</div>
    </div>
  </aside>
</div>


        </section>
    </div>
</main>

<style>
#governanca-app{
  --bg:#081018;--panel:#101a24;--panel2:#15222e;--panel3:#1a2a37;
  --line:#2b4050;--line2:#3a5669;--text:#e7eef5;--muted:#8ea4b5;
  --blue:#3a8ec1;--blue2:#1d4f73;--green:#62d08b;--greenbg:#153725;
  --cyan:#77c7ee;--cyanbg:#17384d;--purple:#b69cff;--purplebg:#30254d;
  --yellow:#efca67;--yellowbg:#3a3016;--red:#ff8d84;--redbg:#461f1f;
  --shadow:0 12px 30px rgba(0,0,0,.28)
}
#governanca-app *{box-sizing:border-box}
#governanca-app{margin:0;background:
 radial-gradient(circle at 15% -10%,rgba(58,142,193,.12),transparent 28%),
 radial-gradient(circle at 90% 0%,rgba(106,78,170,.08),transparent 25%),
 var(--bg);color:var(--text);font-family:Segoe UI,Arial,sans-serif}
#governanca-app header{padding:19px 24px;background:linear-gradient(135deg,#091720,#15405e);border-bottom:1px solid #294355}
#governanca-app header h1{margin:0 0 4px;font-size:23px}
#governanca-app header p{margin:0;color:#9fb6c7;font-size:12px}
#governanca-app .toolbar{position:sticky;top:0;z-index:20;display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:10px 16px;background:#0d1720;border-bottom:1px solid #263b49;box-shadow:0 5px 18px rgba(0,0,0,.2)}
#governanca-app button{border:1px solid #385367;background:#14222d;color:#dce9f2;border-radius:8px;padding:8px 11px;font-weight:650;cursor:pointer}
#governanca-app button:hover{background:#1d3140;border-color:#4e7288}
#governanca-app button.active{background:#1d4f73;border-color:#4f9bca;color:#fff}
#governanca-app .search{margin-left:auto;min-width:280px;max-width:520px;flex:1;border:1px solid #385367;background:#0d1720;color:#e7eef5;border-radius:8px;padding:9px 12px}
#governanca-app .search:focus{outline:none;border-color:#4f9bca;box-shadow:0 0 0 3px rgba(58,142,193,.15)}
#governanca-app .filebox{display:none;margin:0;padding:12px 16px;background:#302914;border-bottom:1px solid #5c4d23;color:#efd88c;font-size:13px}
#governanca-app .filebox.show{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
#governanca-app .statusbar{padding:10px 16px;display:flex;align-items:center;gap:9px;flex-wrap:wrap;font-size:12px;color:var(--muted)}
#governanca-app .status{padding:5px 8px;border-radius:999px;font-weight:750}
#governanca-app .status.ok{background:var(--greenbg);color:#80dfa2}
#governanca-app .status.warn{background:var(--yellowbg);color:#f2d584}
#governanca-app .status.err{background:var(--redbg);color:#ffaaa4}
#governanca-app .stats{padding:0 16px 13px;display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:9px}
#governanca-app .stat{background:var(--panel);border:1px solid #253947;border-radius:10px;padding:11px 13px;box-shadow:var(--shadow)}
#governanca-app .stat strong{display:block;font-size:20px;color:#7ec1e7}
#governanca-app .stat span{font-size:11px;color:var(--muted)}
#governanca-app .filters{display:none;margin:0 16px 13px;background:var(--panel);border:1px solid #253947;border-radius:10px;padding:12px 14px}
#governanca-app .filters.show{display:block}
#governanca-app .filters h3{font-size:12px;margin:0 0 9px;color:#a8c0d1}
#governanca-app .filter-grid{display:flex;gap:9px 18px;flex-wrap:wrap}
#governanca-app .filter-grid label{display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer}
#governanca-app .filter-grid input{width:16px;height:16px}
#governanca-app .layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(370px,.8fr);gap:14px;padding:0 16px 22px}
#governanca-app .card{background:var(--panel);border:1px solid #253947;border-radius:12px;box-shadow:var(--shadow)}
#governanca-app .head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:13px 15px;border-bottom:1px solid #253947}
#governanca-app .head h2{margin:0;color:#dceaf4;font-size:17px}
#governanca-app .head p{margin:4px 0 0;color:var(--muted);font-size:12px}
#governanca-app .treewrap{padding:18px;min-height:640px;overflow:auto}
#governanca-app .tree, #governanca-app .tree ul{list-style:none;margin:0;padding-left:28px;position:relative}
#governanca-app .tree ul:before{content:"";position:absolute;left:8px;top:0;bottom:12px;border-left:1px solid #2b4454}
#governanca-app .tree li{position:relative;padding:7px 0 7px 24px}
#governanca-app .tree li:before{content:"";position:absolute;left:8px;top:28px;width:17px;border-top:1px solid #2b4454}
#governanca-app .tree>li{padding-left:0}
#governanca-app .tree>li:before{display:none}
#governanca-app .branch-toggle{position:absolute;left:-10px;top:14px;width:22px;height:22px;padding:0;border-radius:6px;border:1px solid #345467;background:#10202b;color:#a9ccdf;font-size:12px;z-index:3}
#governanca-app .tree li.collapsed>ul{display:none}
#governanca-app .tree li.collapsed>.branch-toggle{transform:rotate(-90deg)}
#governanca-app .node{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px 12px;width:min(570px,calc(100vw - 150px));min-width:310px;padding:10px 12px;border:1px solid #334d5e;border-radius:10px;background:#13212c;cursor:pointer;transition:.14s}
#governanca-app .node:hover{transform:translateY(-1px);border-color:#4d7c98;box-shadow:0 5px 14px rgba(0,0,0,.25)}
#governanca-app .node.company{background:#17354d;border-color:#39769d}
#governanca-app .node.area{background:#152530}
#governanca-app .node.third{background:#2e2818;border-color:#69582a}
#governanca-app .node.person{background:#111e28;border-left:4px solid #3a9b7c}
#governanca-app .node.process{background:#101a23;border-left:4px solid #397b9e;min-width:270px}
#governanca-app .node.selected{outline:3px solid #efc84d;outline-offset:2px}
#governanca-app .node-title{font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#governanca-app .node-sub{margin-top:2px;font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#governanca-app .badges{display:flex;align-items:center;justify-content:flex-end;gap:5px;flex-wrap:wrap;max-width:280px}
#governanca-app .node.person .badges{grid-column:1/-1;justify-content:flex-start}
#governanca-app .badge{font-size:10px;font-weight:800;padding:3px 6px;border-radius:999px;white-space:nowrap}
#governanca-app .b-main{background:var(--greenbg);color:#87e1a7}
#governanca-app .b-support{background:var(--cyanbg);color:#89d0f2}
#governanca-app .b-backup{background:var(--purplebg);color:#c4b0ff}
#governanca-app .b-count{background:#263946;color:#c5d3dc}
#governanca-app .b-third{background:var(--yellowbg);color:#ecd17b}
#governanca-app .detail{padding:16px;position:sticky;top:62px;max-height:calc(100vh - 85px);overflow:auto}
#governanca-app .detail h3{margin:0;color:#e4eff6}
#governanca-app .sub{font-size:12px;color:var(--muted);margin-top:4px}
#governanca-app .section{margin:17px 0 7px;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#829aab;font-weight:800}
#governanca-app .item{border:1px solid #2b3e4b;border-radius:9px;background:#13202a;padding:9px 10px;margin:7px 0}
#governanca-app .itemtop{display:flex;justify-content:space-between;gap:10px}
#governanca-app .item strong{font-size:13px}
#governanca-app .item small{display:block;color:#8da2b3;margin-top:4px;line-height:1.35}
#governanca-app .empty{padding:32px;text-align:center;color:var(--muted)}
#governanca-app .hidden-by-filter{display:none!important}
#governanca-app .legend{display:flex;gap:5px;flex-wrap:wrap}
#governanca-app ::-webkit-scrollbar{width:10px;height:10px}
#governanca-app ::-webkit-scrollbar-track{background:#0c151d}
#governanca-app ::-webkit-scrollbar-thumb{background:#304b5d;border:2px solid #0c151d;border-radius:10px}
@media (max-width:1000px){#governanca-app .layout{grid-template-columns:1fr}
#governanca-app .detail{position:static;max-height:none}
#governanca-app .stats{grid-template-columns:repeat(2,1fr)}
#governanca-app .search{order:3;min-width:100%}
#governanca-app .node{min-width:260px;width:min(540px,calc(100vw - 110px))}}
#governanca-app .process-summary{
  margin-top:8px;
  display:flex;
  flex-wrap:wrap;
  gap:6px;
}
#governanca-app .process-chip{
  display:inline-flex;
  align-items:center;
  gap:5px;
  max-width:210px;
  padding:5px 8px;
  border:1px solid #2f4a5d;
  background:#10202b;
  color:#bcd1de;
  border-radius:999px;
  font-size:11px;
  cursor:pointer;
}
#governanca-app .process-chip:hover{background:#173044;border-color:#4f7b96}
#governanca-app .process-chip .chip-count{
  min-width:18px;
  height:18px;
  padding:0 5px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border-radius:999px;
  background:#263d4e;
  color:#dbe8f0;
  font-weight:800;
  font-size:10px;
}
#governanca-app .more-chip{
  border-color:#5b4d7d;
  background:#251d36;
  color:#c6b7ff;
}
#governanca-app .person-block{
  display:block;
}
#governanca-app .person-meta{
  margin-top:7px;
  color:#7f96a7;
  font-size:11px;
}
#governanca-app .detail-toolbar{
  position:sticky;
  top:-16px;
  z-index:3;
  margin:-16px -16px 12px;
  padding:12px 16px;
  background:linear-gradient(180deg,#101a24 85%,rgba(16,26,36,0));
}
#governanca-app .detail-filters{
  display:flex;
  gap:6px;
  flex-wrap:wrap;
  margin-top:10px;
}
#governanca-app .detail-filter{
  border:1px solid #365164;
  background:#13232f;
  color:#c7d7e2;
  padding:5px 8px;
  border-radius:999px;
  font-size:11px;
  font-weight:700;
  cursor:pointer;
}
#governanca-app .detail-filter:hover{background:#193242}
#governanca-app .detail-filter.active{background:#1d4f73;border-color:#56a2cf;color:#fff}
#governanca-app .detail-grid{
  display:grid;
  gap:8px;
}
#governanca-app .detail-process{
  margin-top:14px;
  border-top:1px solid #233745;
  padding-top:11px;
}
#governanca-app .detail-process:first-child{border-top:none;padding-top:0;margin-top:0}
#governanca-app .detail-process-title{
  display:flex;
  justify-content:space-between;
  gap:10px;
  align-items:center;
  margin-bottom:7px;
  color:#9fc7df;
  font-size:12px;
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:.04em;
}
#governanca-app{
    width:100%;
    min-height:100%;
    background:
      radial-gradient(circle at 15% -10%,rgba(58,142,193,.10),transparent 28%),
      radial-gradient(circle at 90% 0%,rgba(106,78,170,.07),transparent 25%),
      #081018;
    border-radius:16px;
    overflow:hidden;
    color:#e7eef5;
}
#governanca-app .toolbar{
    position:relative;
    top:auto;
}
#governanca-app .layout{
    min-height:560px;
}
@media(max-width:1000px){
    #governanca-app .layout{grid-template-columns:1fr}
}

/* =========================================================
   LAYOUT FIXO / SCROLL INTERNO
   A página não cresce ao expandir a árvore.
   ========================================================= */
#governanca-app{
    height:100%;
    min-height:0;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}

#governanca-app .toolbar,
#governanca-app .filebox,
#governanca-app .statusbar,
#governanca-app .stats,
#governanca-app .filters{
    flex:0 0 auto;
}

#governanca-app .toolbar{
    position:relative;
    top:auto;
}

#governanca-app .layout{
    flex:1 1 auto;
    min-height:0;
    overflow:hidden;
    padding-bottom:14px;
}

#governanca-app .layout > .card{
    min-height:0;
    height:100%;
    overflow:hidden;
    display:flex;
    flex-direction:column;
}

#governanca-app .layout > .card > .head{
    flex:0 0 auto;
}

#governanca-app .treewrap{
    flex:1 1 auto;
    min-height:0;
    height:auto;
    overflow:auto;
    overscroll-behavior:contain;
    scrollbar-gutter:stable;
}

/* Painel direito também rola sozinho */
#governanca-app .detail{
    position:static;
    top:auto;
    flex:1 1 auto;
    min-height:0;
    height:100%;
    max-height:none;
    overflow:auto;
    overscroll-behavior:contain;
    scrollbar-gutter:stable;
}

/* Mantém o cabeçalho dos detalhes visível enquanto rola o painel direito */
#governanca-app .detail-toolbar{
    position:sticky;
    top:0;
    z-index:4;
    margin:-16px -16px 12px;
    padding:14px 16px 12px;
    background:linear-gradient(180deg,#101a24 88%,rgba(16,26,36,.96));
    border-bottom:1px solid #233745;
}

/* Scrolls um pouco mais discretos */
#governanca-app .treewrap::-webkit-scrollbar,
#governanca-app .detail::-webkit-scrollbar{
    width:9px;
    height:9px;
}

#governanca-app .treewrap::-webkit-scrollbar-thumb,
#governanca-app .detail::-webkit-scrollbar-thumb{
    background:#36576b;
    border:2px solid #0d1720;
    border-radius:999px;
}

/* Desktop: ocupa a tela restante da intranet.
   Mobile: volta a permitir altura natural para não apertar demais. */
@media (max-width:1000px){
    #governanca-app{
        height:auto;
        min-height:720px;
        overflow:visible;
    }

    #governanca-app .layout{
        overflow:visible;
        min-height:0;
    }

    #governanca-app .layout > .card{
        height:auto;
        min-height:420px;
    }

    #governanca-app .treewrap,
    #governanca-app .detail{
        max-height:560px;
        overflow:auto;
    }
}

</style>

<script>
(function(){
const DATA_ENDPOINT = "api/governanca_dados.php";
let DATA={estrutura:[],pessoas:[],responsabilidades:[],relacionamentos:[]};
let VIEW="tree", HIDE_EMPTY=false;
const VIS_KEY="governanca_tree_visibility_v4";

const btnTree = document.getElementById('btnTree');
const btnPeople = document.getElementById('btnPeople');
const btnFilters = document.getElementById('btnFilters');
const filebox = document.getElementById('filebox');
const fileboxMessage = document.getElementById('fileboxMessage');
const loadStatus = document.getElementById('loadStatus');
const lastLoad = document.getElementById('lastLoad');
const sResp = document.getElementById('sResp');
const sPeople = document.getElementById('sPeople');
const sStruct = document.getElementById('sStruct');
const sMain = document.getElementById('sMain');
const sSupport = document.getElementById('sSupport');
const filters = document.getElementById('filters');
const filterGrid = document.getElementById('filterGrid');
const treeTitle = document.getElementById('treeTitle');
const treeSub = document.getElementById('treeSub');
const treeArea = document.getElementById('treeArea');
const detail = document.getElementById('detail');
const search = document.getElementById('search');


function esc(s){return String(s??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m]))}
function clean(v){return v==null?"":String(v).trim()}
function norm(s){return clean(s).normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase().replace(/[^a-z0-9]/g,"")}
function active(x){return !["nao","inativo"].includes(norm(x.ATIVO))}
function roleClass(r){const n=norm(r);if(n==="principal")return"b-main";if(n==="backup")return"b-backup";return"b-support"}
function badge(t,c){return `<span class="badge ${c}">${esc(t)}</span>`}

/* XLSX mínimo */
function u16(d,o){return d[o]|(d[o+1]<<8)}
function u32(d,o){return(d[o]|(d[o+1]<<8)|(d[o+2]<<16)|(d[o+3]<<24))>>>0}
function decode(b){return new TextDecoder("utf-8").decode(b)}
function xml(s){return new DOMParser().parseFromString(s,"application/xml")}
function tags(e,n){return e.getElementsByTagName(n)}
async function inflateRaw(bytes){
  if(!("DecompressionStream" in window))throw new Error("Use Edge/Chrome atualizado.");
  const ds=new DecompressionStream("deflate-raw");
  return new Uint8Array(await new Response(new Blob([bytes]).stream().pipeThrough(ds)).arrayBuffer())
}
async function unzip(ab){
  const d=new Uint8Array(ab);let e=-1;
  for(let i=d.length-22;i>=Math.max(0,d.length-65557);i--)if(u32(d,i)===0x06054b50){e=i;break}
  if(e<0)throw new Error("XLSX inválido.");
  const total=u16(d,e+10),off=u32(d,e+16),files={};let p=off;
  for(let n=0;n<total;n++){
    if(u32(d,p)!==0x02014b50)break;
    const method=u16(d,p+10),cs=u32(d,p+20),fl=u16(d,p+28),el=u16(d,p+30),cl=u16(d,p+32),lo=u32(d,p+42);
    const name=decode(d.slice(p+46,p+46+fl)).replace(/\\/g,"/");
    const lfl=u16(d,lo+26),lel=u16(d,lo+28),start=lo+30+lfl+lel,comp=d.slice(start,start+cs);
    files[name]=method===0?comp:method===8?await inflateRaw(comp):new Uint8Array();
    p+=46+fl+el+cl;
  }return files
}
function resolvePath(base,target){
  target=String(target||"").replace(/\\/g,"/");
  if(target.startsWith("/")) return target.replace(/^\/+/, "");
  const a=base.split("/");
  a.pop();
  target.split("/").forEach(x=>{
    if(!x || x===".") return;
    if(x==="..") a.pop();
    else a.push(x);
  });
  return a.join("/");
}
function collectText(e){let s="";[...tags(e,"t")].forEach(t=>s+=t.textContent||"");return s}
function colNum(s){let n=0;for(const c of s)n=n*26+c.charCodeAt(0)-64;return n-1}
function toRows(cells){
  let mr=0,mc=0;for(const ref in cells){const m=ref.match(/^([A-Z]+)(\d+)$/);if(!m)continue;mc=Math.max(mc,colNum(m[1]));mr=Math.max(mr,+m[2]-1)}
  const rows=Array.from({length:mr+1},()=>Array(mc+1).fill(""));
  for(const[ref,v]of Object.entries(cells)){const m=ref.match(/^([A-Z]+)(\d+)$/);if(m)rows[+m[2]-1][colNum(m[1])]=v}
  return rows
}
async function parseXlsx(ab){
  const f=await unzip(ab),w=xml(decode(f["xl/workbook.xml"])),rels=xml(decode(f["xl/_rels/workbook.xml.rels"])),rm={};
  [...tags(rels,"Relationship")].forEach(r=>rm[r.getAttribute("Id")]=r.getAttribute("Target"));
  let shared=[];if(f["xl/sharedStrings.xml"]){const s=xml(decode(f["xl/sharedStrings.xml"]));shared=[...tags(s,"si")].map(collectText)}
  const out={};
  for(const sh of [...tags(w,"sheet")]){
    const name=sh.getAttribute("name"),rid=sh.getAttribute("r:id")||sh.getAttributeNS("http://schemas.openxmlformats.org/officeDocument/2006/relationships","id");
    const bytes=f[resolvePath("xl/workbook.xml",rm[rid])];if(!bytes)continue;
    const sx=xml(decode(bytes)),cells={};
    for(const c of [...tags(sx,"c")]){
      const ref=c.getAttribute("r"),type=c.getAttribute("t")||"";let val="";
      if(type==="inlineStr"){const is=tags(c,"is")[0];val=is?collectText(is):""}
      else{const ve=tags(c,"v")[0],raw=ve?ve.textContent:"";val=type==="s"?(shared[+raw]??""):type==="b"?(raw==="1"?"Sim":"Não"):raw}
      cells[ref]=val
    }out[name]=toRows(cells)
  }return out
}
function objects(rows){
  if(!rows?.length)return[];
  const h=rows[0].map(clean);
  return rows.slice(1).filter(r=>r.some(x=>clean(x)!=="")).map(r=>{const o={};h.forEach((x,i)=>{if(x)o[x]=clean(r[i])});return o})
}
function sheet(s,w){const k=Object.keys(s).find(x=>norm(x)===norm(w));return k?s[k]:[]}
function buildData(s){
  const estrutura=objects(sheet(s,"ESTRUTURA")),pessoas=objects(sheet(s,"PESSOAS")),responsabilidades=objects(sheet(s,"RESPONSABILIDADES")),relacionamentos=objects(sheet(s,"RELACIONAMENTOS"));
  const pmap=Object.fromEntries(pessoas.map(p=>[p.ID_PESSOA,p]));
  responsabilidades.forEach(r=>{const p=pmap[r.ID_PESSOA];if(p){if(!r["PESSOA (AUTOMÁTICO)"])r["PESSOA (AUTOMÁTICO)"]=p.NOME;if(!r["FUNÇÃO (AUTOMÁTICO)"])r["FUNÇÃO (AUTOMÁTICO)"]=p["FUNÇÃO / CARGO"]}});
  return{estrutura,pessoas,responsabilidades,relacionamentos}
}

function applyServerData(payload){
  DATA = payload?.data || {estrutura:[],pessoas:[],responsabilidades:[],relacionamentos:[]};

  if(!DATA.estrutura.length || !DATA.pessoas.length || !DATA.responsabilidades.length){
    throw new Error(
      "Dados incompletos recebidos do servidor. "+
      "ESTRUTURA="+DATA.estrutura.length+
      ", PESSOAS="+DATA.pessoas.length+
      ", RESPONSABILIDADES="+DATA.responsabilidades.length
    );
  }

  setStatus("ok","Dados carregados");
  const atualizacao = payload?.source?.modified_at || new Date().toLocaleString("pt-BR");
  lastLoad.textContent = "Última leitura: " + atualizacao;
  filebox.classList.remove("show");

  updateStats();
  buildFilters();
  setView(VIEW);
}

async function reloadWorkbook(){
  setStatus("warn","Atualizando...");
  filebox.classList.remove("show");

  try{
    const response = await fetch(DATA_ENDPOINT + "?t=" + Date.now(), {
      cache:"no-store",
      credentials:"same-origin",
      headers:{"Accept":"application/json"}
    });

    let payload = null;
    try{
      payload = await response.json();
    }catch(_){
      throw new Error("O servidor respondeu em formato inválido.");
    }

    if(!response.ok || !payload?.ok){
      throw new Error(payload?.error || ("HTTP " + response.status));
    }

    applyServerData(payload);
  }catch(e){
    console.error("Governança:", e);
    setStatus("err","Falha ao carregar");
    fileboxMessage.textContent = e?.message || "Erro desconhecido.";
    filebox.classList.add("show");
    treeArea.innerHTML = `<div class="empty">
      <b>Não foi possível carregar a Governança.</b><br><br>
      ${esc(e?.message || "Erro desconhecido.")}
    </div>`;
  }
}

function setStatus(c,t){loadStatus.className="status "+c;loadStatus.textContent=t}

/* Árvore genérica */
function maps(){
  const sm=Object.fromEntries(DATA.estrutura.map(x=>[x.ID,x])),pm=Object.fromEntries(DATA.pessoas.map(x=>[x.ID_PESSOA,x])),ch={};
  DATA.estrutura.filter(active).forEach(x=>{const p=x.ID_PAI||"__root__";(ch[p]??=[]).push(x)});
  Object.values(ch).forEach(a=>a.sort((x,y)=>(+x.ORDEM||99)-(+y.ORDEM||99)));
  return{sm,pm,ch}
}
function descendants(id,ch){
  const out=[];(ch[id]||[]).forEach(c=>{out.push(c.ID,...descendants(c.ID,ch))});return out
}
function directResp(id){return DATA.responsabilidades.filter(r=>r.ID_ESTRUTURA===id)}
function subtreeResp(id,ch){const ids=new Set([id,...descendants(id,ch)]);return DATA.responsabilidades.filter(r=>ids.has(r.ID_ESTRUTURA))}
function directMembers(id){return DATA.pessoas.filter(p=>p.ID_ESTRUTURA===id&&active(p)).sort((a,b)=>(+a.ORDEM||99)-(+b.ORDEM||99))}
function personRespAt(pid,id){return DATA.responsabilidades.filter(r=>r.ID_PESSOA===pid&&r.ID_ESTRUTURA===id)}
function personsWithRespAt(id,pm){
  const ids=[...new Set(directResp(id).map(r=>r.ID_PESSOA).filter(Boolean))];
  return ids.map(x=>pm[x]).filter(Boolean).sort((a,b)=>(+a.ORDEM||99)-(+b.ORDEM||99))
}
function personBadges(rows){
  const c={};rows.forEach(r=>{const k=r.PAPEL||"Sem papel";c[k]=(c[k]||0)+1});
  return Object.entries(c).map(([k,v])=>badge(`${v} ${k.toLowerCase()}`,roleClass(k))).join("")
}
function node(title,sub,cls,type,id,badges=""){
  return `<span class="node ${cls}" data-type="${esc(type)}" data-id="${esc(id)}" onclick="Gov.selectNode(event,this)">
    <span><div class="node-title" title="${esc(title)}">${esc(title)}</div>${sub?`<div class="node-sub" title="${esc(sub)}">${esc(sub)}</div>`:""}</span>
    <span class="badges">${badges}</span></span>`
}
function li(content,kids="",branch=""){
  const toggle=kids?`<button class="branch-toggle" onclick="Gov.toggleBranch(event,this)">▼</button>`:"";
  return `<li ${branch?`data-branch="${esc(branch)}"`:""}>${toggle}${content}${kids?`<ul>${kids}</ul>`:""}</li>`
}
function toggleBranch(e,b){e.stopPropagation();b.closest("li").classList.toggle("collapsed")}

function personCard(p,nodeId){
  const rs=personRespAt(p.ID_PESSOA,nodeId);
  return node(p.NOME,p["FUNÇÃO / CARGO"]||"","person","person-node",`${p.ID_PESSOA}|${nodeId}`,personBadges(rs)+badge(rs.length,"b-count"))
}
function personProcessSummary(p,nodeId,limit=5){
  const rows=personRespAt(p.ID_PESSOA,nodeId),groups={};
  rows.forEach(r=>{
    const k=r.PROCESSO||r.MACROPROCESSO||"Atividades";
    (groups[k]??=[]).push(r);
  });
  const entries=Object.entries(groups).sort((a,b)=>b[1].length-a[1].length||a[0].localeCompare(b[0],"pt-BR"));
  const shown=entries.slice(0,limit);
  const hidden=Math.max(0,entries.length-limit);

  let html=`<div class="person-meta">${entries.length} processo(s) · ${rows.length} responsabilidade(s)</div>`;
  if(shown.length){
    html+=`<div class="process-summary">`+
      shown.map(([k,v])=>`<button class="process-chip" onclick="Gov.openProcessFromTree(event,'${esc(p.ID_PESSOA)}','${esc(nodeId)}','${esc(k)}')" title="${esc(k)}">${esc(k)} <span class="chip-count">${v.length}</span></button>`).join("")+
      (hidden?`<button class="process-chip more-chip" onclick="Gov.openPersonNodeDetails(event,'${esc(p.ID_PESSOA)}','${esc(nodeId)}')">+ ${hidden} processo(s)</button>`:"")+
      `</div>`;
  }
  return html;
}
function renderStruct(s,ch,pm){
  const kids=ch[s.ID]||[],resp=subtreeResp(s.ID,ch),direct=directResp(s.ID),third=norm(s["VÍNCULO"]).includes("terceiro");
  const cls=third?"third":norm(s["TIPO_NÓ"])==="empresa"?"company":"area";
  let inner=kids.map(k=>renderStructLi(k,ch,pm)).join("");

  const assigned=personsWithRespAt(s.ID,pm);
  const memberOnly=directMembers(s.ID).filter(p=>{
    if(assigned.some(a=>a.ID_PESSOA===p.ID_PESSOA))return false;
    const childIds=new Set(descendants(s.ID,ch));
    return !DATA.responsabilidades.some(r=>r.ID_PESSOA===p.ID_PESSOA&&childIds.has(r.ID_ESTRUTURA));
  });

  assigned.forEach(p=>{
    inner+=li(
      `<div class="person-block">${personCard(p,s.ID)}${personProcessSummary(p,s.ID,5)}</div>`
    );
  });
  memberOnly.forEach(p=>inner+=li(node(p.NOME,p["FUNÇÃO / CARGO"]||"","person","person",p.ID_PESSOA,badge("vinculado","b-count"))));

  const b=(third?badge(s["VÍNCULO"]||"Terceiro","b-third"):"")+badge(resp.length,"b-count");
  return node(s.NOME,s["RESPONSÁVEL / EMPRESA"]||"",cls,"structure",s.ID,b)+ (inner?`<ul>${inner}</ul>`:"")
}
function renderStructLi(s,ch,pm){return `<li data-branch="${esc(s.ID)}">${(ch[s.ID]?.length||personsWithRespAt(s.ID,pm).length||directMembers(s.ID).length)?`<button class="branch-toggle" onclick="Gov.toggleBranch(event,this)">▼</button>`:""}${renderStruct(s,ch,pm)}</li>`}

function renderTree(){
  const{pm,ch}=maps(),roots=(ch["__root__"]||[]),internal=roots.filter(x=>!norm(x["VÍNCULO"]).includes("terceiro")),third=roots.filter(x=>norm(x["VÍNCULO"]).includes("terceiro"));
  let h=internal.map(r=>`<li>${renderStruct(r,ch,pm)}</li>`).join("");
  if(third.length){
    const tkids=third.map(t=>renderStructLi(t,ch,pm)).join("");
    h+=li(node("Terceiros de T.I","Relacionamentos externos","third","third-group","thirds",badge(third.length,"b-third")),tkids,"thirds");
  }
  treeArea.innerHTML=`<ul class="tree">${h}</ul>`;applyFilters()
}
function renderPeople(){
  const{sm}=maps();
  let h="";
  DATA.pessoas.filter(active).sort((a,b)=>(+a.ORDEM||99)-(+b.ORDEM||99)).forEach(p=>{
    const rs=DATA.responsabilidades.filter(r=>r.ID_PESSOA===p.ID_PESSOA);
    const structGroups={};
    rs.forEach(r=>{
      const s=sm[r.ID_ESTRUTURA];
      const key=s?.ID||r.ID_ESTRUTURA;
      if(!structGroups[key]) structGroups[key]={name:s?.NOME||r.ID_ESTRUTURA,rows:[]};
      structGroups[key].rows.push(r);
    });

    let summary=`<div class="person-meta">${Object.keys(structGroups).length} frente(s) · ${rs.length} responsabilidade(s)</div>`;
    summary+=`<div class="process-summary">`;
    Object.entries(structGroups).slice(0,5).forEach(([sid,g])=>{
      summary+=`<button class="process-chip" onclick="Gov.openPersonNodeDetails(event,'${esc(p.ID_PESSOA)}','${esc(sid)}')">${esc(g.name)} <span class="chip-count">${g.rows.length}</span></button>`;
    });
    if(Object.keys(structGroups).length>5){
      summary+=`<button class="process-chip more-chip" onclick="Gov.openAllPersonDetails(event,'${esc(p.ID_PESSOA)}')">+ ${Object.keys(structGroups).length-5} frente(s)</button>`;
    }
    summary+=`</div>`;
    h+=li(`<div class="person-block">${node(p.NOME,p["FUNÇÃO / CARGO"]||"","person","person",p.ID_PESSOA,badge(rs.length,"b-count"))}${summary}</div>`);
  });
  treeArea.innerHTML=`<ul class="tree">${h}</ul>`;
}

function setView(v){
  VIEW=v;btnTree.classList.toggle("active",v==="tree");btnPeople.classList.toggle("active",v==="people");
  if(v==="tree"){treeTitle.textContent="Estrutura oficial";treeSub.textContent="A árvore mostra estrutura, pessoas e até 5 processos por pessoa; atividades completas ficam no painel direito.";renderTree()}
  else{treeTitle.textContent="Pessoas e frentes";treeSub.textContent="Resumo por colaborador; detalhes e atividades completas ficam no painel direito.";renderPeople()}
  detail.innerHTML='<h3>Detalhes</h3><div class="sub">Clique em uma estrutura, pessoa ou processo.</div>'
}

/* Detalhes */
function showRows(title,sub,rows,initialProcess=""){
  const groups={};
  rows.forEach(r=>{
    const k=r.PROCESSO||r.MACROPROCESSO||"Atividades";
    (groups[k]??=[]).push(r);
  });
  const processes=Object.keys(groups).sort((a,b)=>a.localeCompare(b,"pt-BR"));
  const selected=initialProcess&&groups[initialProcess]?initialProcess:"";

  let h=`<div class="detail-toolbar">
    <h3>${esc(title)}</h3>
    <div class="sub">${esc(sub||"")}</div>
    <div class="section" style="margin-top:10px">${rows.length} responsabilidade(s) · ${processes.length} processo(s)</div>`;

  if(processes.length>1){
    h+=`<div class="detail-filters">
      <button class="detail-filter ${selected?"":"active"}" onclick="Gov.filterDetailProcess(event,'')">Todos</button>`+
      processes.map(p=>`<button class="detail-filter ${selected===p?"active":""}" onclick="Gov.filterDetailProcess(event,'${esc(p)}')">${esc(p)} (${groups[p].length})</button>`).join("")+
      `</div>`;
  }
  h+=`</div><div id="detailRows" class="detail-grid">`;

  if(!rows.length){
    h+=`<div class="empty">Nenhuma responsabilidade vinculada.</div>`;
  }else{
    for(const p of processes){
      h+=`<div class="detail-process" data-detail-process="${esc(p)}" ${selected&&selected!==p?'style="display:none"':""}>
        <div class="detail-process-title"><span>${esc(p)}</span><span class="badge b-count">${groups[p].length}</span></div>`;
      groups[p].forEach(r=>{
        h+=`<div class="item">
          <div class="itemtop">
            <strong>${esc(r.ATIVIDADE||r.SUBATIVIDADE||"Sem atividade")}</strong>
            ${badge(r.PAPEL||"Sem papel",roleClass(r.PAPEL))}
          </div>
          <small>${esc(r["PESSOA (AUTOMÁTICO)"]||"")} · ${esc(r["FUNÇÃO (AUTOMÁTICO)"]||"")} ${r.DOMÍNIO?`· ${esc(r.DOMÍNIO)}`:""} ${r.CRITICIDADE?`· ${esc(r.CRITICIDADE)}`:""}</small>
        </div>`;
      });
      h+=`</div>`;
    }
  }
  h+=`</div>`;
  detail.innerHTML=h;
}

function filterDetailProcess(e,process){
  e?.stopPropagation();
  document.querySelectorAll(".detail-filter").forEach(b=>b.classList.remove("active"));
  e?.currentTarget?.classList.add("active");
  document.querySelectorAll("[data-detail-process]").forEach(box=>{
    box.style.display=(!process||box.dataset.detailProcess===process)?"":"none";
  });
}
function openProcessFromTree(e,pid,sid,process){
  e.stopPropagation();
  const{pm,sm}=maps();
  const p=pm[pid],s=sm[sid];
  showRows(p?.NOME||pid,`${p?.["FUNÇÃO / CARGO"]||""} · ${s?.NOME||sid}`,personRespAt(pid,sid),process);
}
function openPersonNodeDetails(e,pid,sid){
  e.stopPropagation();
  const{pm,sm}=maps();
  const p=pm[pid],s=sm[sid];
  showRows(p?.NOME||pid,`${p?.["FUNÇÃO / CARGO"]||""} · ${s?.NOME||sid}`,personRespAt(pid,sid));
}
function openAllPersonDetails(e,pid){
  e.stopPropagation();
  const{pm}=maps(),p=pm[pid];
  showRows(p?.NOME||pid,p?.["FUNÇÃO / CARGO"]||"",DATA.responsabilidades.filter(r=>r.ID_PESSOA===pid));
}

function selectNode(e,el){
  e.stopPropagation();document.querySelectorAll(".node.selected").forEach(x=>x.classList.remove("selected"));el.classList.add("selected");
  const t=el.dataset.type,id=el.dataset.id,{sm,pm,ch}=maps();
  if(t==="structure"){const s=sm[id];showRows(s?.NOME||id,s?.["VÍNCULO"]||"",subtreeResp(id,ch));const rel=DATA.relacionamentos.filter(r=>r.ID_ORIGEM===id||r.ID_DESTINO===id);if(rel.length)detail.innerHTML+=`<div class="section">Relacionamentos</div>`+rel.map(r=>`<div class="item"><strong>${esc(r.TIPO_RELACAO)}</strong><small>${esc(r.DESCRIÇÃO)}</small></div>`).join("")}
  else if(t==="person-node"){const[pid,sid]=id.split("|"),p=pm[pid],s=sm[sid];showRows(p?.NOME||pid,`${p?.["FUNÇÃO / CARGO"]||""} · ${s?.NOME||sid}`,personRespAt(pid,sid))}
  else if(t==="person"){const p=pm[id];showRows(p?.NOME||id,p?.["FUNÇÃO / CARGO"]||"",DATA.responsabilidades.filter(r=>r.ID_PESSOA===id))}
  else if(t==="process"){const[pid,sid,proc]=id.split("|");showRows(proc,pm[pid]?.NOME||"",personRespAt(pid,sid).filter(r=>(r.PROCESSO||r.MACROPROCESSO||"Atividades")===proc))}
}

/* Filtros */
function buildFilters(){
  const{ch}=maps(),cs=DATA.estrutura.find(x=>x.ID==="CS")||DATA.estrutura.find(x=>!x.ID_PAI&&!norm(x["VÍNCULO"]).includes("terceiro"));
  const top=cs?(ch[cs.ID]||[]):[];
  filterGrid.innerHTML=top.map(s=>`<label><input type="checkbox" data-filter="${esc(s.ID)}" checked onchange="Gov.saveFilters()"> ${esc(s.NOME)}</label>`).join("")+
    `<label><input type="checkbox" data-filter="thirds" checked onchange="Gov.saveFilters()"> Terceiros</label>`;
  restoreFilters()
}
function toggleFilters(){filters.classList.toggle("show");btnFilters.classList.toggle("active",filters.classList.contains("show"))}
function filterState(){const o={};document.querySelectorAll("[data-filter]").forEach(i=>o[i.dataset.filter]=i.checked);return o}
function saveFilters(){localStorage.setItem(VIS_KEY,JSON.stringify(filterState()));applyFilters()}
function restoreFilters(){try{const s=JSON.parse(localStorage.getItem(VIS_KEY)||"{}");document.querySelectorAll("[data-filter]").forEach(i=>{if(typeof s[i.dataset.filter]==="boolean")i.checked=s[i.dataset.filter]})}catch(e){}}
function showAll(){document.querySelectorAll("[data-filter]").forEach(i=>i.checked=true);HIDE_EMPTY=false;saveFilters()}
function hideEmptyToggle(){HIDE_EMPTY=!HIDE_EMPTY;applyFilters()}
function applyFilters(){
  if(VIEW!=="tree")return;const st=filterState();
  document.querySelectorAll("#treeArea [data-branch]").forEach(li=>{if(Object.hasOwn(st,li.dataset.branch))li.classList.toggle("hidden-by-filter",st[li.dataset.branch]===false)});
  if(HIDE_EMPTY){
    const{ch}=maps();
    document.querySelectorAll("#treeArea .node[data-type='structure']").forEach(n=>{
      const id=n.dataset.id,li=n.closest("li"),hasPeople=directMembers(id).length||personsWithRespAt(id,maps().pm).length;
      if(subtreeResp(id,ch).length===0&&!hasPeople)li?.classList.add("hidden-by-filter")
    })
  }
}
function expandAll(){document.querySelectorAll("#treeArea ul").forEach(x=>x.style.display="");document.querySelectorAll("#treeArea li").forEach(x=>x.classList.remove("collapsed"))}
function collapseAll(){document.querySelectorAll("#treeArea li").forEach(li=>{if(li.querySelector(":scope > ul"))li.classList.add("collapsed")})}
function runSearch(){
  const q=norm(search.value);document.querySelectorAll(".node.selected").forEach(x=>x.classList.remove("selected"));if(!q)return;expandAll();
  const nodes=[...document.querySelectorAll("#treeArea .node")].filter(n=>norm(n.textContent).includes(q));nodes.forEach(n=>n.classList.add("selected"));
  const rows=DATA.responsabilidades.filter(r=>Object.values(r).some(v=>norm(v).includes(q)));if(rows.length)showRows(`Pesquisa: ${search.value}`,`${rows.length} resultado(s)`,rows);nodes[0]?.scrollIntoView({behavior:"smooth",block:"center"})
}
function updateStats(){sResp.textContent=DATA.responsabilidades.length;sPeople.textContent=DATA.pessoas.filter(active).length;sStruct.textContent=DATA.estrutura.filter(active).length;sMain.textContent=DATA.responsabilidades.filter(r=>norm(r.PAPEL)==="principal").length;sSupport.textContent=DATA.responsabilidades.filter(r=>["apoio","backup"].includes(norm(r.PAPEL))).length}

window.Gov = {
  setView,
  expandAll,
  collapseAll,
  toggleFilters,
  reloadWorkbook,
  runSearch,
  showAll,
  hideEmptyToggle,
  toggleBranch,
  selectNode,
  openProcessFromTree,
  openPersonNodeDetails,
  openAllPersonDetails,
  filterDetailProcess,
  saveFilters
};

reloadWorkbook();

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
