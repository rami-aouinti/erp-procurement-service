import {
  ArrowRight, BookOpen, Building2, CheckCircle2, CircleHelp, ClipboardCheck,
  FileCog, KeyRound, LifeBuoy, LockKeyhole, Search, Settings2,
  ShieldCheck, Users, Workflow,
} from 'lucide-react';
import '../help-page.css';

const guides = [
  {
    id: 'suppliers',
    icon: Building2,
    number: '01',
    title: 'Supplier management',
    summary: 'Maintain a reliable, searchable supplier directory.',
    steps: [
      'Open Suppliers to review the supplier directory. Use search and the available filters to narrow the list.',
      'Select Create supplier and complete the guided Company, Contact and Address steps.',
      'Review the information before submitting. A new registration is sent for approval; it is not automatically an approved supplier.',
      'Open a supplier record to review its profile. Use the edit action when you need to correct information.',
    ],
    tip: 'Keep company names, tax identifiers and contact details consistent to make supplier records easier to find and maintain.',
  },
  {
    id: 'approvals',
    icon: ClipboardCheck,
    number: '02',
    title: 'Onboarding and approvals',
    summary: 'Review submitted supplier registrations and record a clear decision.',
    steps: [
      'Users with the supplier-reviewer or supplier-admin role can open Approvals.',
      'Review the submitted supplier information before making a decision.',
      'Choose approve, reject or request changes. A reason is required when rejecting a request or asking for changes.',
      'Use the available history to understand previous review decisions.',
    ],
    tip: 'Write concise, actionable reasons so the person responsible for the supplier record knows exactly what needs attention.',
  },
  {
    id: 'classification',
    icon: Workflow,
    number: '03',
    title: 'Supplier classification',
    summary: 'Organise suppliers using shared procurement classifications.',
    steps: [
      'Users with the supplier-admin role can access Classifications.',
      'Maintain the available categories, commodities, tags and business units.',
      'Use strategic tiers such as Standard, Preferred and Strategic where applicable.',
      'Apply consistent classification values so supplier records can be filtered and reviewed consistently.',
    ],
    tip: 'Agree classification definitions with your procurement team before using them across the supplier directory.',
  },
  {
    id: 'configuration',
    icon: Settings2,
    number: '04',
    title: 'Configuration and preferences',
    summary: 'Separate workspace-wide settings from personal appearance choices.',
    steps: [
      'Administrators can manage shared workspace settings, company details, locale, currency, timezone and form requirements.',
      'Form settings define which supplier details are required when a record is submitted.',
      'Every signed-in user can choose a personal color palette and light, dark or system appearance.',
      'Personal appearance preferences are saved to the user account; they do not change the interface for colleagues.',
    ],
    tip: 'Before making a required-field change, inform the people who create supplier records so they can prepare the requested information.',
  },
];

const faqs = [
  {
    question: 'Why can I not see Approvals or Classifications?',
    answer: 'These areas are permission-controlled. Approvals requires the supplier-reviewer or supplier-admin role. Classifications requires supplier-admin. Contact your application administrator if you believe you need access.',
  },
  {
    question: 'Why is a newly created supplier not active?',
    answer: 'New supplier registrations are submitted for review. A reviewer must process the onboarding request before the supplier can move through the approval workflow.',
  },
  {
    question: 'Why can I not submit a supplier form?',
    answer: 'Check that all required fields are completed and that the email address is valid. Some required fields are controlled by workspace configuration.',
  },
  {
    question: 'What should I do if the API is unavailable?',
    answer: 'Check the System status indicator in the sidebar. If the API is unavailable, refresh after a short wait. If the issue continues, contact your technical support team and include the time of the error and the action you were performing.',
  },
  {
    question: 'Who can change shared settings?',
    answer: 'Shared workspace configuration is restricted to users with the supplier-admin role. Personal appearance preferences are available to each signed-in user.',
  },
];

const quickLinks = [
  { href: '#suppliers', label: 'Manage suppliers', icon: Building2 },
  { href: '#approvals', label: 'Review onboarding', icon: ClipboardCheck },
  { href: '#classification', label: 'Supplier classification', icon: Workflow },
  { href: '#configuration', label: 'Configure workspace', icon: FileCog },
  { href: '#faq', label: 'Troubleshooting & FAQ', icon: CircleHelp },
];

function GuideCard({ guide }) {
  const Icon = guide.icon;
  return (
    <article className="help-guide-card" id={guide.id}>
      <div className="help-guide-heading">
        <span className="help-guide-icon"><Icon size={19} /></span>
        <span className="help-guide-number">{guide.number}</span>
      </div>
      <h3>{guide.title}</h3>
      <p className="help-guide-summary">{guide.summary}</p>
      <ol className="help-step-list">
        {guide.steps.map((step) => <li key={step}>{step}</li>)}
      </ol>
      <div className="help-tip"><CheckCircle2 size={16} /><p><strong>Recommended practice</strong>{guide.tip}</p></div>
    </article>
  );
}

export default function HelpPage({ appName }) {
  return (
    <section className="page-content help-page">
      <header className="help-hero">
        <div className="help-hero-copy">
          <span className="help-eyebrow"><BookOpen size={14} /> KNOWLEDGE CENTRE</span>
          <h1>Help &amp; Support</h1>
          <p>Guidance for managing supplier information, reviewing onboarding requests and maintaining your procurement workspace.</p>
          <div className="help-hero-meta"><span><ShieldCheck size={14} /> Role-based access</span><span><Users size={14} /> Workspace guidance</span></div>
        </div>
        <div className="help-hero-art" aria-hidden="true"><div className="help-hero-orbit orbit-one" /><div className="help-hero-orbit orbit-two" /><span><LifeBuoy size={42} /></span></div>
      </header>

      <div className="help-content-grid">
        <aside className="help-quick-nav">
          <div className="help-quick-title"><Search size={15} /> QUICK ACCESS</div>
          {quickLinks.map(({ href, label, icon: Icon }) => <a href={href} key={href}><Icon size={16} /><span>{label}</span><ArrowRight size={14} /></a>)}
          <div className="help-quick-note"><strong>{appName || 'ERP Procurement'}</strong><span>Application knowledge base</span></div>
        </aside>

        <div className="help-main-content">
          <section className="help-section-intro">
            <span className="help-section-kicker">GETTING STARTED</span>
            <h2>Make the most of your workspace</h2>
            <p>Use the guides below to understand the main workflows, the permissions required and the recommended practices for keeping procurement data consistent.</p>
          </section>

          <div className="help-guides-grid">
            {guides.map((guide) => <GuideCard key={guide.id} guide={guide} />)}
          </div>

          <section className="help-security-panel" id="security">
            <div className="help-security-icon"><LockKeyhole size={21} /></div>
            <div>
              <span className="help-section-kicker">ACCOUNT &amp; SECURITY</span>
              <h2>Use your access responsibly</h2>
              <p>Sign in with your organisation's configured identity account. Access to administrative and review workflows is determined by assigned application roles.</p>
              <ul>
                <li>Never share your password or active sign-in session.</li>
                <li>Only access supplier and company information required for your work.</li>
                <li>Request role changes through your application administrator.</li>
                <li>Sign out when using a shared or public workstation.</li>
              </ul>
            </div>
          </section>

          <section className="help-faq-section" id="faq">
            <div className="help-section-intro">
              <span className="help-section-kicker">COMMON QUESTIONS</span>
              <h2>Frequently asked questions</h2>
              <p>Quick answers to common access, data-entry and service availability questions.</p>
            </div>
            <div className="help-faq-list">
              {faqs.map((item) => <details className="help-faq-item" key={item.question}><summary>{item.question}<span>+</span></summary><p>{item.answer}</p></details>)}
            </div>
          </section>

          <section className="help-support-panel">
            <div className="help-support-icon"><LifeBuoy size={20} /></div>
            <div><h2>Need further assistance?</h2><p>Contact your internal application administrator or service desk. Include the page, the action you attempted, the approximate time and any error message. Do not include passwords, access tokens or sensitive supplier data in support requests.</p></div>
          </section>

          <footer className="help-footer"><span><KeyRound size={14} /> Access depends on your assigned role.</span><span>Documentation for the current application scope.</span></footer>
        </div>
      </div>
    </section>
  );
}
