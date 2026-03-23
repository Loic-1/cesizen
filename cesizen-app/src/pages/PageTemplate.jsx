export default function PageTemplate({ eyebrow, title, description }) {
  return (
    <main className="page">
      <section className="page__content">
        <p className="eyebrow">{eyebrow}</p>
        <h1>{title}</h1>
        <p>{description}</p>
      </section>
    </main>
  )
}
