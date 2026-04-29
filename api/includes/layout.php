<?php
/**
 * includes/layout.php
 * Shell layout utama — dipakai oleh semua halaman inner via ob_start() pattern.
 * Variabel yang harus di-set sebelum require:
 *   $pageTitle   (string)  — judul tab browser
 *   $activeMenu  (string)  — kunci menu sidebar yang aktif
 *   $pageContent (string)  — HTML konten halaman dari ob_get_clean()
 * Variabel opsional:
 *   $extraHead   (string)  — tag <link>/<meta> tambahan di <head>
 *   $extraScript (string)  — tag <script> tambahan sebelum </body>
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? 'Halaman') ?> — <?= APP_NAME ?></title>
    <style>
/* === app.css === */


@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

:root {
  --navy:        #0F2744;
  --navy-light:  #1a3a5c;
  --blue:        #1976D2;
  --blue-light:  #2196F3;
  --blue-pale:   #E3F0FB;
  --blue-muted:  #BBDEFB;
  --green:       #2E7D32;
  --green-bg:    #E8F5E9;
  --green-border:#A5D6A7;
  --amber:       #E65100;
  --amber-bg:    #FFF3E0;
  --amber-border:#FFCC80;
  --red:         #C62828;
  --red-bg:      #FFEBEE;
  --red-border:  #EF9A9A;
  --purple-bg:   #F3E5F5;
  --purple:      #6A1B9A;
  --white:       #FFFFFF;
  --gray-50:     #FAFAFA;
  --gray-100:    #F5F5F5;
  --gray-200:    #EEEEEE;
  --gray-300:    #E0E0E0;
  --gray-400:    #BDBDBD;
  --gray-500:    #9E9E9E;
  --gray-600:    #757575;
  --gray-700:    #616161;
  --gray-800:    #424242;
  --gray-900:    #212121;
  --font:        'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --sidebar-w:   248px;
  --topbar-h:    56px;
  --gap:         20px;
  --r-sm:        4px;
  --r:           6px;
  --r-md:        8px;
  --r-lg:        10px;
  --shadow-xs:   0 1px 2px rgba(0,0,0,.07);
  --shadow-sm:   0 1px 4px rgba(0,0,0,.09);
  --shadow:      0 2px 8px rgba(0,0,0,.10);
  --shadow-md:   0 4px 16px rgba(0,0,0,.10);
  --ease:        .15s ease;
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:14px;-webkit-font-smoothing:antialiased}
body{font-family:var(--font);background:var(--gray-100);color:var(--gray-900);line-height:1.55}
a{color:var(--blue);text-decoration:none}
a:hover{text-decoration:underline}
button,input,select,textarea{font-family:inherit;font-size:inherit}
img{max-width:100%;display:block}

/* LAYOUT */
.app-layout{display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{width:var(--sidebar-w);height:100vh;position:fixed;top:0;left:0;background:var(--navy);display:flex;flex-direction:column;z-index:200;overflow-y:auto;overflow-x:hidden}
.sidebar::-webkit-scrollbar{width:3px}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:4px}
.sidebar-brand{display:flex;align-items:center;gap:10px;padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.08)}
.brand-icon{width:32px;height:32px;background:var(--blue);border-radius:var(--r);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.brand-icon svg{width:18px;height:18px}
.brand-name{font-size:15px;font-weight:700;color:var(--white);letter-spacing:.2px}
.sidebar-user{display:flex;align-items:center;gap:10px;padding:13px 20px;border-bottom:1px solid rgba(255,255,255,.08)}
.user-avatar{width:34px;height:34px;border-radius:var(--r);background:var(--blue);color:var(--white);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;letter-spacing:.5px}
.user-name{font-size:13px;font-weight:600;color:rgba(255,255,255,.9);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px;line-height:1.2}
.user-role{font-size:11px;font-weight:500;padding:2px 7px;border-radius:20px;display:inline-block;margin-top:3px}
.badge-admin{background:rgba(255,193,7,.2);color:#FFD54F}
.badge-dokter{background:rgba(33,150,243,.2);color:#90CAF9}
.badge-perawat{background:rgba(76,175,80,.2);color:#A5D6A7}
.badge-pasien{background:rgba(255,255,255,.1);color:rgba(255,255,255,.6)}
.sidebar-nav{flex:1;padding:10px 12px}
.nav-section-label{font-size:11px;font-weight:700;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.1em;padding:10px 8px 5px}
.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r);color:rgba(255,255,255,.65);font-size:13px;font-weight:500;transition:background var(--ease),color var(--ease);margin-bottom:1px;cursor:pointer;border:none;background:none;width:100%;text-align:left;text-decoration:none}
.nav-item:hover{background:rgba(255,255,255,.07);color:rgba(255,255,255,.9);text-decoration:none}
.nav-item.active{background:var(--blue);color:var(--white)}
.nav-icon{width:16px;height:16px;flex-shrink:0;opacity:.7}
.nav-item.active .nav-icon{opacity:1}
.sidebar-footer{padding:12px;border-top:1px solid rgba(255,255,255,.08)}
.nav-item-logout{color:rgba(239,154,154,.8)!important}
.nav-item-logout:hover{background:rgba(198,40,40,.15)!important;color:#EF9A9A!important}

/* MAIN CONTENT */
.main-content{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;min-width:0}

/* TOPBAR */
.topbar{height:var(--topbar-h);background:var(--white);border-bottom:1px solid var(--gray-300);display:flex;align-items:center;justify-content:space-between;padding:0 24px;position:sticky;top:0;z-index:100}
.topbar-title{font-size:15px;font-weight:600;color:var(--gray-900)}
.topbar-actions{display:flex;align-items:center;gap:12px}
.topbar-date{font-size:13px;color:var(--gray-500)}

/* PAGE CONTENT */
.page-content{padding:24px;flex:1}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap}
.page-title{font-size:20px;font-weight:700;color:var(--gray-900);letter-spacing:-.2px}
.page-subtitle{font-size:13px;color:var(--gray-500);margin-top:2px}

/* STATS GRID */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px}
.stat-card{background:var(--white);border:1px solid var(--gray-300);border-radius:var(--r-md);padding:18px 20px;display:flex;align-items:center;gap:14px;transition:border-color var(--ease)}
.stat-card:hover{border-color:var(--blue-muted)}
.stat-icon{width:42px;height:42px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.stat-icon svg{width:20px;height:20px}
.stat-icon.blue{background:var(--blue-pale);color:var(--blue)}
.stat-icon.green{background:var(--green-bg);color:var(--green)}
.stat-icon.amber{background:var(--amber-bg);color:var(--amber)}
.stat-icon.red{background:var(--red-bg);color:var(--red)}
.stat-icon.navy{background:#E8EFF7;color:var(--navy)}
.stat-body{flex:1;min-width:0}
.stat-value{font-size:24px;font-weight:700;color:var(--gray-900);line-height:1;letter-spacing:-.5px}
.stat-label{font-size:11px;color:var(--gray-500);font-weight:500;margin-top:4px;text-transform:uppercase;letter-spacing:.04em}

/* CARDS */
.card{background:var(--white);border:1px solid var(--gray-300);border-radius:var(--r-md)}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;border-bottom:1px solid var(--gray-200)}
.card-title{font-size:11px;font-weight:700;color:var(--gray-900);text-transform:uppercase;letter-spacing:.04em}
.card-body{padding:18px}
.card-footer{padding:12px 18px;border-top:1px solid var(--gray-200);background:var(--gray-50);border-radius:0 0 var(--r-md) var(--r-md)}

/* TABLES */
.table-wrapper{overflow-x:auto}
table.data-table{width:100%;border-collapse:collapse;font-size:13px}
.data-table thead th{background:var(--gray-50);padding:9px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--gray-300);white-space:nowrap}
.data-table tbody tr{border-bottom:1px solid var(--gray-200)}
.data-table tbody tr:hover{background:var(--gray-50)}
.data-table tbody tr:last-child{border-bottom:none}
.data-table tbody td{padding:11px 14px;color:var(--gray-800);vertical-align:middle}
.td-name{font-weight:600;color:var(--gray-900)}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:var(--r-sm);font-size:11px;font-weight:600;letter-spacing:.02em;border:1px solid transparent}
.badge-waiting{background:var(--amber-bg);color:var(--amber);border-color:var(--amber-border)}
.badge-called{background:var(--blue-pale);color:var(--blue);border-color:var(--blue-muted)}
.badge-in_progress{background:var(--purple-bg);color:var(--purple);border-color:#CE93D8}
.badge-done{background:var(--green-bg);color:var(--green);border-color:var(--green-border)}
.badge-cancelled{background:var(--gray-100);color:var(--gray-600);border-color:var(--gray-300)}
.badge-published{background:var(--green-bg);color:var(--green);border-color:var(--green-border)}
.badge-draft{background:var(--gray-100);color:var(--gray-600);border-color:var(--gray-300)}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--r);font-size:13px;font-weight:600;cursor:pointer;border:1px solid transparent;text-decoration:none;transition:background var(--ease),border-color var(--ease),color var(--ease);white-space:nowrap;line-height:1.4}
.btn:hover{text-decoration:none}
.btn-primary{background:var(--blue);color:var(--white);border-color:var(--blue)}
.btn-primary:hover{background:var(--navy-light);border-color:var(--navy-light);color:var(--white)}
.btn-navy{background:var(--navy);color:var(--white);border-color:var(--navy)}
.btn-navy:hover{background:var(--navy-light);color:var(--white)}
.btn-success{background:var(--green);color:var(--white);border-color:var(--green)}
.btn-success:hover{background:#1B5E20;color:var(--white)}
.btn-danger{background:var(--red);color:var(--white);border-color:var(--red)}
.btn-danger:hover{background:#B71C1C;color:var(--white)}
.btn-outline{background:var(--white);border:1px solid var(--gray-400);color:var(--gray-700)}
.btn-outline:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-pale)}
.btn-ghost{background:transparent;border-color:transparent;color:var(--gray-600)}
.btn-ghost:hover{background:var(--gray-100);color:var(--gray-900)}
.btn-sm{padding:5px 10px;font-size:11px}
.btn-lg{padding:10px 20px;font-size:15px}
.btn-icon{width:30px;height:30px;padding:0;justify-content:center}
.btn svg{width:14px;height:14px}
.btn-sm svg{width:12px;height:12px}
.btn:disabled{opacity:.5;cursor:not-allowed}

/* FORMS */
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:5px}
.form-label .req{color:var(--red);margin-left:2px}
.form-control{width:100%;padding:8px 11px;border:1px solid var(--gray-400);border-radius:var(--r);font-size:13px;font-family:var(--font);color:var(--gray-900);background:var(--white);transition:border-color var(--ease),box-shadow var(--ease);outline:none;line-height:1.5}
.form-control:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(25,118,210,.12)}
.form-control:disabled,.form-control[readonly]{background:var(--gray-100);color:var(--gray-600);cursor:not-allowed}
.form-control::placeholder{color:var(--gray-400)}
textarea.form-control{resize:vertical;min-height:80px}
select.form-control{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 20 20' fill='%23757575'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px}
.form-hint{font-size:11px;color:var(--gray-500);margin-top:4px}
.form-error-text{font-size:11px;color:var(--red);margin-top:4px}

/* ALERTS */
.alert{display:flex;align-items:flex-start;gap:10px;padding:11px 14px;border-radius:var(--r);font-size:13px;margin-bottom:16px;border-width:1px;border-style:solid}
.alert svg{width:16px;height:16px;flex-shrink:0;margin-top:1px}
.alert-success{background:var(--green-bg);color:var(--green);border-color:var(--green-border)}
.alert-error{background:var(--red-bg);color:var(--red);border-color:var(--red-border)}
.alert-warning{background:var(--amber-bg);color:var(--amber);border-color:var(--amber-border)}
.alert-info{background:var(--blue-pale);color:var(--blue);border-color:var(--blue-muted)}

/* SEARCH BAR */
.search-bar{display:flex;align-items:center;gap:7px;background:var(--white);border:1px solid var(--gray-400);border-radius:var(--r);padding:6px 10px;transition:border-color var(--ease),box-shadow var(--ease)}
.search-bar:focus-within{border-color:var(--blue);box-shadow:0 0 0 3px rgba(25,118,210,.12)}
.search-bar svg{width:14px;height:14px;color:var(--gray-400);flex-shrink:0}
.search-bar input{border:none;background:transparent;font-size:13px;color:var(--gray-900);font-family:var(--font);outline:none;width:200px}
.search-bar input::placeholder{color:var(--gray-400)}

/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;visibility:hidden;transition:opacity .2s ease,visibility .2s ease}
.modal-overlay.open{opacity:1;visibility:visible}
.modal{background:var(--white);border-radius:var(--r-lg);width:100%;max-width:540px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-md);transform:scale(.97) translateY(6px);transition:transform .2s ease}
.modal-overlay.open .modal{transform:scale(1) translateY(0)}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--gray-200)}
.modal-title{font-size:15px;font-weight:700;color:var(--gray-900)}
.modal-close{width:26px;height:26px;border-radius:var(--r-sm);border:1px solid var(--gray-300);background:var(--white);color:var(--gray-500);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background var(--ease)}
.modal-close:hover{background:var(--red-bg);color:var(--red);border-color:var(--red-border)}
.modal-body{padding:20px}
.modal-footer{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--gray-200);background:var(--gray-50)}

/* QUEUE */
.queue-card{background:var(--white);border:1px solid var(--gray-300);border-radius:var(--r-md);padding:14px 16px;display:flex;align-items:center;gap:14px;transition:border-color var(--ease)}
.queue-card:hover{border-color:var(--blue-muted)}
.queue-number{width:46px;height:46px;border-radius:var(--r);background:var(--blue);color:var(--white);font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.queue-number.waiting{background:var(--amber)}
.queue-number.in_progress{background:var(--purple)}
.queue-number.done{background:var(--green)}

/* VITALS */
.vitals-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:16px}
.vital-item{background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--r);padding:12px 14px;text-align:center}
.vital-value{font-size:20px;font-weight:700;color:var(--gray-900);letter-spacing:-.3px}
.vital-unit{font-size:11px;color:var(--gray-500);font-weight:400}
.vital-label{font-size:11px;color:var(--gray-500);margin-top:3px}

/* TIMELINE */
.record-timeline{position:relative;padding-left:20px}
.record-timeline::before{content:'';position:absolute;left:6px;top:10px;bottom:10px;width:2px;background:var(--gray-300)}
.timeline-item{position:relative;padding-bottom:20px}
.timeline-item::before{content:'';position:absolute;left:-18px;top:7px;width:8px;height:8px;border-radius:50%;background:var(--blue);border:2px solid var(--white);box-shadow:0 0 0 2px var(--blue-muted)}
.timeline-date{font-size:11px;font-weight:700;color:var(--gray-500);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em}
.timeline-card{background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--r);padding:12px 14px}

/* PATIENT HEADER */
.patient-header{background:var(--white);border:1px solid var(--gray-300);border-radius:var(--r-md);padding:18px 22px;display:flex;align-items:center;gap:16px;margin-bottom:18px;flex-wrap:wrap}
.patient-avatar{width:52px;height:52px;border-radius:var(--r-md);background:var(--navy);color:var(--white);font-size:17px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.patient-meta{display:flex;gap:20px;flex-wrap:wrap}
.patient-meta-item{display:flex;flex-direction:column;gap:2px}
.meta-label{font-size:11px;color:var(--gray-500);font-weight:700;text-transform:uppercase;letter-spacing:.06em}
.meta-value{font-size:13px;font-weight:600;color:var(--gray-800)}

/* EMPTY STATE */
.empty-state{text-align:center;padding:40px 20px}
.empty-state-icon{width:56px;height:56px;border-radius:var(--r-md);background:var(--gray-100);color:var(--gray-400);display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.empty-state-icon svg{width:28px;height:28px}
.empty-state-title{font-size:15px;font-weight:600;color:var(--gray-600);margin-bottom:6px}
.empty-state-desc{font-size:13px;color:var(--gray-400);max-width:280px;margin:0 auto 14px}

/* INFO DL */
.info-dl dt{font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.06em;margin-top:14px}
.info-dl dt:first-child{margin-top:0}
.info-dl dd{font-size:13px;color:var(--gray-800);font-weight:500;margin-top:2px}

/* QUICK ACTIONS */
.quick-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px}
.quick-action-btn{display:flex;flex-direction:column;align-items:flex-start;gap:10px;padding:16px;background:var(--white);border:1px solid var(--gray-300);border-radius:var(--r-md);cursor:pointer;transition:border-color var(--ease),background var(--ease);text-decoration:none;color:var(--gray-800)}
.quick-action-btn:hover{border-color:var(--blue);background:var(--blue-pale);text-decoration:none;color:var(--blue)}
.quick-action-icon{width:36px;height:36px;border-radius:var(--r);background:var(--blue-pale);color:var(--blue);display:flex;align-items:center;justify-content:center}
.quick-action-icon svg{width:18px;height:18px}
.quick-action-label{font-size:13px;font-weight:600}

/* PATIENT AUTOCOMPLETE */
.inline-patient-search{position:relative}
.patient-dropdown{position:absolute;top:100%;left:0;right:0;background:var(--white);border:1px solid var(--gray-300);border-top:none;border-radius:0 0 var(--r) var(--r);box-shadow:var(--shadow);z-index:50;max-height:220px;overflow-y:auto;display:none}
.patient-dropdown.open{display:block}
.patient-option{padding:9px 12px;cursor:pointer;transition:background var(--ease);border-bottom:1px solid var(--gray-100)}
.patient-option:hover{background:var(--blue-pale)}
.patient-option:last-child{border-bottom:none}
.patient-option-name{font-size:13px;font-weight:600;color:var(--gray-900)}
.patient-option-sub{font-size:11px;color:var(--gray-500)}

/* CHART */
.chart-container{position:relative;height:240px}

/* SPINNER & LIVE DOT */
.live-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--green);animation:pulse-dot 2s ease-in-out infinite}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(1.3)}}
.spinner{width:16px;height:16px;border:2px solid var(--gray-300);border-top-color:var(--blue);border-radius:50%;animation:spin .6s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}

/* SECTION DIVIDER */
.section-divider{height:1px;background:var(--gray-200);margin:18px 0}

/* UTILITIES */
.flex{display:flex}.flex-col{display:flex;flex-direction:column}.items-center{align-items:center}.items-start{align-items:flex-start}.justify-between{justify-content:space-between}.justify-end{justify-content:flex-end}.gap-1{gap:4px}.gap-2{gap:8px}.gap-3{gap:12px}.gap-4{gap:16px}.flex-wrap{flex-wrap:wrap}.flex-1{flex:1}.min-w-0{min-width:0}.w-full{width:100%}.mt-1{margin-top:4px}.mt-2{margin-top:8px}.mt-4{margin-top:16px}.mt-6{margin-top:24px}.mb-2{margin-bottom:8px}.mb-4{margin-bottom:16px}.mb-6{margin-bottom:24px}.ml-auto{margin-left:auto}.p-4{padding:16px}.text-xs{font-size:11px}.text-sm{font-size:13px}.text-base{font-size:14px}.text-lg{font-size:17px}.font-medium{font-weight:500}.font-semibold{font-weight:600}.font-bold{font-weight:700}.text-muted{color:var(--gray-500)}.text-danger{color:var(--red)}.text-success{color:var(--green)}.text-navy{color:var(--navy)}.text-blue{color:var(--blue)}.truncate{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* GRID */
.two-col-grid{display:grid;grid-template-columns:1fr 1fr;gap:var(--gap)}
.three-col-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--gap)}

/* TOGGLE */
#sidebarToggle{display:none}

/* RESPONSIVE */
@media(max-width:1100px){:root{--sidebar-w:220px}}
@media(max-width:768px){#sidebarToggle{display:flex}.sidebar{transform:translateX(-100%);transition:transform .2s ease}.sidebar.open{transform:translateX(0);box-shadow:var(--shadow-md)}.main-content{margin-left:0}.two-col-grid,.three-col-grid{grid-template-columns:1fr}.stats-grid{grid-template-columns:1fr 1fr}.page-content{padding:16px}.page-header{flex-direction:column}.patient-header{flex-direction:column;align-items:flex-start}}
@media(max-width:480px){.stats-grid{grid-template-columns:1fr}.search-bar input{width:120px}}


/* === inner.css === */

.doctor-card {
  background: var(--white);
  border: 1px solid var(--gray-300);
  border-radius: var(--r-md);
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: border-color var(--ease);
}

.doctor-card:hover { border-color: var(--blue-muted); }

.doctor-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--navy);
  color: var(--white);
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.doctor-name { font-size: 14px; font-weight: 700; color: var(--gray-900); }
.doctor-spec { font-size: 12px; color: var(--gray-500); margin-top: 2px; }
.doctor-status { margin-left: auto; }

/* DASHBOARD GREETING BANNER */
.greeting-banner {
  background: var(--navy);
  border-radius: var(--r-md);
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 16px;
}

.greeting-text h2 {
  font-size: 17px;
  font-weight: 700;
  color: var(--white);
  margin-bottom: 4px;
}

.greeting-text p {
  font-size: 13px;
  color: rgba(255,255,255,.6);
}

.greeting-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,.1);
  border-radius: var(--r);
  padding: 8px 14px;
  font-size: 13px;
  color: rgba(255,255,255,.85);
  font-weight: 600;
  white-space: nowrap;
}

/* SECTION HEADER inline */
.section-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.section-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--gray-600);
  text-transform: uppercase;
  letter-spacing: .06em;
}

/* CALL PATIENT CARD (dokter dashboard) */
.call-card {
  background: var(--white);
  border: 1px solid var(--gray-300);
  border-radius: var(--r-md);
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 8px;
  transition: border-color var(--ease);
}

.call-card:hover { border-color: var(--blue-muted); }
.call-card:last-child { margin-bottom: 0; }

.call-num {
  width: 40px;
  height: 40px;
  border-radius: var(--r);
  background: var(--amber-bg);
  color: var(--amber);
  border: 1px solid var(--amber-border);
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.call-info { flex: 1; min-width: 0; }
.call-patient { font-size: 14px; font-weight: 600; color: var(--gray-900); }
.call-sub { font-size: 12px; color: var(--gray-500); margin-top: 2px; }

/* ACTIVE QUEUE BANNER (pasien) */
.queue-banner {
  background: var(--blue-pale);
  border: 1px solid var(--blue-muted);
  border-radius: var(--r-md);
  padding: 20px 22px;
  display: flex;
  align-items: center;
  gap: 18px;
  margin-bottom: 20px;
}

.queue-big-number {
  font-size: 40px;
  font-weight: 700;
  color: var(--blue);
  letter-spacing: -2px;
  line-height: 1;
  flex-shrink: 0;
}

.queue-banner-info h3 { font-size: 16px; font-weight: 700; color: var(--gray-900); margin-bottom: 4px; }
.queue-banner-info p { font-size: 13px; color: var(--gray-600); }

/* STATS CARDS ADDITIONAL — narrow pill */
.stat-pill {
  background: var(--white);
  border: 1px solid var(--gray-300);
  border-radius: var(--r);
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}

.stat-pill-val { font-size: 18px; font-weight: 700; color: var(--gray-900); }
.stat-pill-label { font-size: 12px; color: var(--gray-500); }

/* ALERT ALLERGY BADGE */
.allergy-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--red-bg);
  color: var(--red);
  border: 1px solid var(--red-border);
  border-radius: var(--r-sm);
  padding: 3px 8px;
  font-size: 11px;
  font-weight: 700;
}

/* VITAL ABNORMAL HIGHLIGHT */
.vital-item.abnormal {
  border-color: var(--amber-border);
  background: var(--amber-bg);
}
.vital-item.abnormal .vital-value { color: var(--amber); }

/* PRINT */
@media print {
  .sidebar, .topbar, .btn, .page-header .btn { display: none !important; }
  .main-content { margin-left: 0 !important; }
  .card { border: 1px solid #ccc; }
}

</style>
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>
<div class="app-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="flex items-center gap-2">
                <button id="sidebarToggle" class="btn btn-ghost btn-icon">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <span class="topbar-title"><?= sanitize($pageTitle ?? '') ?></span>
            </div>
            <div class="topbar-actions">
                <span class="topbar-date"><?= date('d F Y') ?></span>
                <span id="liveClock" class="text-sm font-semibold" style="color:var(--gray-600)"></span>
            </div>
        </div>
        <!-- Page Content -->
        <div class="page-content">
            <?php echo $pageContent ?? ''; ?>
        </div>
    </div>
</div>
<script>
// MediRek — Main JS

document.addEventListener('DOMContentLoaded', () => {

    // ---- Auto-dismiss alerts ----
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 4500);
    });

    // ---- Confirm delete/action buttons ----
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!confirm(btn.dataset.confirm || 'Yakin ingin menghapus data ini?')) {
                e.preventDefault();
            }
        });
    });

    // ---- Modal system ----
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.dataset.modalOpen);
            if (modal) modal.classList.add('open');
        });
    });

    document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-overlay')?.classList.remove('open');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // ---- Mobile sidebar toggle ----
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // ---- Live clock in topbar ----
    const clockEl = document.getElementById('liveClock');
    if (clockEl) {
        const update = () => {
            const now = new Date();
            clockEl.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        };
        update();
        setInterval(update, 10000);
    }

    // ---- Demo account fill (login page) ----
    document.querySelectorAll('.demo-row').forEach(row => {
        row.addEventListener('click', () => {
            const email = row.dataset.email;
            const pwd = row.dataset.password;
            const emailField = document.getElementById('email');
            const pwdField = document.getElementById('password');
            if (emailField) emailField.value = email;
            if (pwdField) { pwdField.value = pwd; pwdField.type = 'text'; setTimeout(() => pwdField.type='password', 600); }
        });
    });

    // ---- Inline patient search autocomplete (new.php) ----
    const patientSearch = document.getElementById('patientSearch');
    const patientDropdown = document.getElementById('patientDropdown');
    const patientIdInput = document.getElementById('patientId');

    if (patientSearch && patientDropdown) {
        let debounceTimer;
        patientSearch.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const q = patientSearch.value.trim();
                if (q.length < 2) { patientDropdown.classList.remove('open'); return; }
                try {
                    // FIX: gunakan path relatif dari BASE_URL, tidak hardcode /medirek/
                    const res = await fetch(`/apb/search_patients?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
                    const data = await res.json();
                    patientDropdown.innerHTML = '';
                    if (!data.length) {
                        patientDropdown.innerHTML = '<div class="patient-option text-muted">Tidak ditemukan</div>';
                    } else {
                        data.forEach(p => {
                            const div = document.createElement('div');
                            div.className = 'patient-option';
                            div.innerHTML = `<div class="patient-option-name">${escHtml(p.name)}</div><div class="patient-option-sub">NIK: ${escHtml(p.nik)} &bull; ${p.gender === 'L' ? 'Laki-laki' : 'Perempuan'} &bull; ${p.age} th</div>`;
                            div.addEventListener('click', () => {
                                patientSearch.value = p.name;
                                if (patientIdInput) patientIdInput.value = p.id;
                                patientDropdown.classList.remove('open');
                                // Enable submit button
                                const submitBtn = document.getElementById('submitBtn');
                                if (submitBtn) submitBtn.disabled = false;
                                // Trigger patient info update
                                if (typeof onPatientSelected === 'function') onPatientSelected(p);
                                // Update info card for new.php
                                const infoCard = document.getElementById('patientInfoCard');
                                const headerRow = document.getElementById('patientHeaderRow');
                                const infoAvatar = document.getElementById('infoAvatar');
                                const infoName = document.getElementById('infoName');
                                const infoSub = document.getElementById('infoSub');
                                const infoAllergy = document.getElementById('infoAllergy');
                                if (infoCard) infoCard.style.display = 'block';
                                if (headerRow) headerRow.style.display = 'flex';
                                if (infoAvatar) infoAvatar.textContent = p.name.substring(0, 2).toUpperCase();
                                if (infoName) infoName.textContent = p.name;
                                if (infoSub) infoSub.textContent = `${p.gender === 'L' ? 'Laki-laki' : 'Perempuan'} \u00b7 ${p.age} tahun`;
                                if (infoAllergy) {
                                    if (p.allergy) {
                                        infoAllergy.textContent = '\u26a0 Alergi: ' + p.allergy;
                                        infoAllergy.style.display = 'inline-flex';
                                    } else {
                                        infoAllergy.style.display = 'none';
                                    }
                                }
                            });
                            patientDropdown.appendChild(div);
                        });
                    }
                    patientDropdown.classList.add('open');
                } catch (_) {}
            }, 280);
        });

        document.addEventListener('click', e => {
            if (!patientSearch.contains(e.target)) patientDropdown.classList.remove('open');
        });
    }

    // ---- Character counter for textareas ----
    document.querySelectorAll('textarea[maxlength]').forEach(ta => {
        const counter = document.createElement('div');
        counter.className = 'text-xs text-muted mt-2';
        const update = () => { counter.textContent = `${ta.value.length} / ${ta.maxLength}`; };
        ta.after(counter);
        ta.addEventListener('input', update);
        update();
    });

    // ---- Queue status quick-update via AJAX ----
    // FIX: gunakan BASE_URL yang benar, tidak hardcode /medirek/
    document.querySelectorAll('[data-queue-action]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const queueId = btn.dataset.queueId;
            const action = btn.dataset.queueAction;
            const confirmMsg = btn.dataset.confirm;

            if (confirmMsg && !confirm(confirmMsg)) return;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>';

            try {
                const res = await fetch('/apb/queue_action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ queue_id: parseInt(queueId), action })
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    showToast('error', data.message || 'Gagal memperbarui antrian');
                    btn.disabled = false;
                    btn.innerHTML = action === 'called' ? 'Panggil' : action === 'in_progress' ? 'Mulai' : 'Batal';
                }
            } catch (_) {
                btn.disabled = false;
                showToast('error', 'Terjadi kesalahan jaringan');
            }
        });
    });
});

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;box-shadow:0 4px 20px rgba(0,0,0,.15);';
    toast.innerHTML = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity .4s'; setTimeout(() => toast.remove(), 400); }, 3500);
}

</script>
<?php if (!empty($extraScript)) echo $extraScript; ?>
</body>
</html>
