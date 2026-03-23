import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
  buildArticleExcerpt,
  fetchArticles,
  formatArticleDate,
} from "../lib/articles.js";

const homeDescription = `
Écrivez ici la description de la page d'accueil de Cesizen.
Vous pouvez remplacer ce texte par votre propre présentation du projet,
de ses objectifs et de l'expérience que vous voulez mettre en avant.
`.trim();

export default function AppPage() {
  const [articles, setArticles] = useState([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let cancelled = false;

    async function loadLatestArticles() {
      setIsLoading(true);
      setError("");

      try {
        const articlesWithFiles = await fetchArticles({ limit: 5 });

        if (!cancelled) {
          setArticles(articlesWithFiles);
          setCurrentIndex(0);
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(loadError.message);
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }

    loadLatestArticles();

    return () => {
      cancelled = true;
    };
  }, []);

  const currentArticle = articles[currentIndex] ?? null;

  function goToPrevious() {
    setCurrentIndex((current) =>
      current === 0 ? articles.length - 1 : current - 1,
    );
  }

  function goToNext() {
    setCurrentIndex((current) =>
      current === articles.length - 1 ? 0 : current + 1,
    );
  }

  return (
    <main className="page page--top">
      <section className="home">
        <article className="home__intro">
          <p className="eyebrow">Accueil</p>
          <h1>Bienvenue sur <span className="cesizen-span">CESIZen</span></h1>
          <p className="home__description">
            {/* {homeDescription} */}
            Le projet <span className="cesizen-span">CESIZen</span> est une plateforme grand public proposant des
            outils de gestion du stress et d’information autour de la santé
            mentale. <br />
            <br />
            La santé mentale est un enjeu majeur en France. Chaque année, une
            personne sur cinq est atteinte de troubles psychiatriques, et
            pourtant, c’est un sujet considéré tabou par la majorité des français.
            <br />
            <br />
            Nous sommes donc fiers de vous présenter <span className="cesizen-span">CESIZen</span>, une plateforme
            disponible à tous les français et proposant des exercices de
            méditation et des articles sur le bien être, dans le but d’améliorer
            le bien-être et la santé mentale des citoyens.
          </p>
          <div className="page__actions page__actions--left">
            <Link to="/coherence-cardiaque" className="button button--primary">
              Exercice de cohérence cardiaque
            </Link>
          </div>
        </article>

        <article className="home__carousel">
          <div className="home__carousel-header">
            <div>
              <p className="eyebrow">À la une</p>
              <h2>Les 5 derniers articles</h2>
            </div>

            <Link to="/articles" className="button button--ghost">
              Voir tous les articles
            </Link>
          </div>

          {isLoading ? (
            <p className="articles-state">Chargement des derniers articles…</p>
          ) : null}
          {error ? (
            <p className="form-feedback form-feedback--error">{error}</p>
          ) : null}

          {!isLoading && !error && !currentArticle ? (
            <p className="articles-state">
              Aucun article n'est disponible pour le moment.
            </p>
          ) : null}

          {!isLoading && !error && currentArticle ? (
            <>
              <div className="carousel-card">
                <Link
                  to={`/articles/${currentArticle.id}`}
                  className="carousel-card__thumbnail-link"
                >
                  <div className="carousel-card__thumbnail-frame">
                    {currentArticle.thumbnailUrl ? (
                      <img
                        src={currentArticle.thumbnailUrl}
                        alt={
                          currentArticle.title ?? "Illustration de l'article"
                        }
                        className="carousel-card__thumbnail"
                      />
                    ) : (
                      <div className="carousel-card__thumbnail carousel-card__thumbnail--empty" />
                    )}
                  </div>
                </Link>

                <div className="carousel-card__body">
                  <div className="article-card__meta">
                    <span>{formatArticleDate(currentArticle.createdAt)}</span>
                    <span>
                      {currentArticle?.user?.email ?? "Auteur inconnu"}
                    </span>
                  </div>

                  <h2>{currentArticle.title ?? "Sans titre"}</h2>
                  <p>{buildArticleExcerpt(currentArticle, 160)}</p>
                </div>
              </div>

              <div className="carousel-controls">
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={goToPrevious}
                >
                  Article précédent
                </button>

                <div
                  className="carousel-dots"
                  aria-label="Sélection des articles"
                >
                  {articles.map((article, index) => (
                    <button
                      key={article.id}
                      type="button"
                      className={
                        index === currentIndex
                          ? "carousel-dots__dot carousel-dots__dot--active"
                          : "carousel-dots__dot"
                      }
                      onClick={() => setCurrentIndex(index)}
                      aria-label={`Afficher l'article ${index + 1}`}
                    />
                  ))}
                </div>

                <button
                  type="button"
                  className="button button--ghost"
                  onClick={goToNext}
                >
                  Article suivant
                </button>
              </div>
            </>
          ) : null}
        </article>
      </section>
    </main>
  );
}
