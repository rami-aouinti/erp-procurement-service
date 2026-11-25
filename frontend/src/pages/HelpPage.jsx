import { BookOpen, Building2, Database, FileText, ShieldCheck } from 'lucide-react';

const topics = [
  { icon: Building2, title: 'Supplier directory', text: 'Browse suppliers, search by name or details, filter by status, and create new supplier records. Open a supplier to view its complete profile or edit its main information.' },
  { icon: FileText, title: 'Supplier profiles', text: 'Each supplier profile groups the company information with its business contacts, addresses and bank account details. Use the profile tabs to manage the related records.' },
  { icon: Database, title: 'Data and persistence', text: 'Supplier data is managed by the Symfony backend and stored in the Oracle database. Changes made through the interface are sent to the backend API.' },
  { icon: ShieldCheck, title: 'Backend connection', text: 'The Backend API indicator in the sidebar shows whether the application can reach the backend health endpoint. If it is unavailable, check that the backend and database services are running.' },
  { icon: BookOpen, title: 'Application settings', text: 'Settings lets you configure the application name, shop information, default currency and the interface color palette. These preferences are currently stored in this browser.' },
];

export default function HelpPage({ appName }) {
  return (
    <section className="page-content help-page">
      <div className="help-card">
        <div className="help-intro"><span className="help-icon"><BookOpen size={22} /></span><div><h1>{appName || 'Procurement Management Suite'}</h1><p>Application guide · What this workspace does and how to use it</p></div></div>
        <div className="help-topic-list">
          {topics.map(({ icon: Icon, title, text }) => <article className="help-topic" key={title}><span className="help-topic-icon"><Icon size={18} /></span><div><h2>{title}</h2><p>{text}</p></div></article>)}
        </div>
        <div className="help-note"><strong>Current scope</strong><p>This application currently focuses on supplier management. Purchase orders, inventory and other procurement workflows are not yet available in the interface.</p></div>
      </div>
    </section>
  );
}
