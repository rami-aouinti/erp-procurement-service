export default function StatusPanel({ status, message }) {
  const label = status === 'online' ? 'Connected' : status === 'offline' ? 'Unavailable' : 'Checking';

  return (
    <section className="status-panel">
      <div>
        <p className="panel-title">Backend connection</p>
        <p className="panel-description">{message}</p>
      </div>
      <span className={`status-pill status-${status}`}>
        <span className="status-dot" />
        {label}
      </span>
    </section>
  );
}
