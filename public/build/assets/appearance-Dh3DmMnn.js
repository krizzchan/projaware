import{u as m,j as e,L as u}from"./app-C_eXWUDx.js";import{c as o}from"./utils-jAU0Cazi.js";import{c as s}from"./createLucideIcon-DvczVFAY.js";import{S as h,H as x}from"./layout-BXGIwUW5.js";import{A as b}from"./app-layout-Bj_VXtyS.js";import"./users-DWLbw_mq.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const y=[["rect",{width:"20",height:"14",x:"2",y:"3",rx:"2",key:"48i651"}],["line",{x1:"8",x2:"16",y1:"21",y2:"21",key:"1svkeh"}],["line",{x1:"12",x2:"12",y1:"17",y2:"21",key:"vw1qmm"}]],g=s("Monitor",y);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const f=[["path",{d:"M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z",key:"a7tn18"}]],k=s("Moon",f);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const j=[["circle",{cx:"12",cy:"12",r:"4",key:"4exip2"}],["path",{d:"M12 2v2",key:"tus03m"}],["path",{d:"M12 20v2",key:"1lh1kg"}],["path",{d:"m4.93 4.93 1.41 1.41",key:"149t6j"}],["path",{d:"m17.66 17.66 1.41 1.41",key:"ptbguv"}],["path",{d:"M2 12h2",key:"1t8f8n"}],["path",{d:"M20 12h2",key:"1q8mjw"}],["path",{d:"m6.34 17.66-1.41 1.41",key:"1m8zz5"}],["path",{d:"m19.07 4.93-1.41 1.41",key:"1shlcs"}]],v=s("Sun",j);function A({className:r="",...i}){const{appearance:a,updateAppearance:n}=m(),c=[{value:"light",icon:v,label:"Light",description:"A bright, clean workspace."},{value:"dark",icon:k,label:"Dark",description:"A softer view in low light."},{value:"system",icon:g,label:"System",description:"Match your device settings."}];return e.jsx("div",{role:"group","aria-label":"Appearance theme",className:o("grid grid-cols-1 gap-3 sm:grid-cols-3",r),...i,children:c.map(({value:t,icon:l,label:d,description:p})=>e.jsxs("button",{type:"button","aria-pressed":a===t,onClick:()=>n(t),className:o("flex min-w-0 flex-col items-start rounded-xl border p-4 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600",a===t?"border-blue-600 bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-200":"border-border bg-card text-card-foreground hover:border-blue-400"),children:[e.jsx(l,{className:"mb-3 h-5 w-5","aria-hidden":"true"}),e.jsx("span",{className:"text-sm font-semibold",children:d}),e.jsx("span",{className:"text-muted-foreground mt-1 text-xs leading-5",children:p})]},t))})}const w=[{title:"Appearance settings",href:"/settings/appearance"}];function z(){return e.jsxs(b,{breadcrumbs:w,children:[e.jsx(u,{title:"Appearance settings"}),e.jsx(h,{children:e.jsxs("div",{className:"space-y-6",children:[e.jsx(x,{title:"Appearance settings",description:"Choose a light, dark, or system theme for your console."}),e.jsx(A,{}),e.jsx("p",{className:"text-muted-foreground text-xs",children:"Saved automatically in this browser. System follows your device’s appearance."})]})})]})}export{z as default};
