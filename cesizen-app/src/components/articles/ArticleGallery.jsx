import { useEffect, useState } from 'react'
import { resolveArticleImages } from '../../lib/articles.js'

export default function ArticleGallery({ articleTitle, files }) {
  const images = resolveArticleImages(files)
  const [currentImageIndex, setCurrentImageIndex] = useState(0)
  const currentImage = images[currentImageIndex] ?? null

  useEffect(() => {
    setCurrentImageIndex(0)
  }, [files])

  function goToPreviousImage() {
    setCurrentImageIndex((current) => (current === 0 ? images.length - 1 : current - 1))
  }

  function goToNextImage() {
    setCurrentImageIndex((current) => (current === images.length - 1 ? 0 : current + 1))
  }

  if (!currentImage) {
    return <div className="article-detail__thumbnail article-detail__thumbnail--empty" />
  }

  return (
    <div className="article-gallery">
      <img
        src={currentImage.url}
        alt={currentImage.alt || articleTitle || "Illustration de l'article"}
        className="article-detail__thumbnail"
      />

      {images.length > 1 ? (
        <>
          <div className="article-gallery__controls">
            <button type="button" className="button button--ghost" onClick={goToPreviousImage}>
              Image précédente
            </button>

            <p className="article-gallery__counter">
              Image {currentImageIndex + 1} sur {images.length}
            </p>

            <button type="button" className="button button--ghost" onClick={goToNextImage}>
              Image suivante
            </button>
          </div>

          <div className="article-gallery__thumbs" aria-label="Galerie des images">
            {images.map((image, index) => (
              <button
                key={image.id}
                type="button"
                className={
                  index === currentImageIndex
                    ? 'article-gallery__thumb article-gallery__thumb--active'
                    : 'article-gallery__thumb'
                }
                onClick={() => setCurrentImageIndex(index)}
                aria-label={`Afficher l'image ${index + 1}`}
              >
                <img src={image.url} alt={image.alt} className="article-gallery__thumb-image" />
              </button>
            ))}
          </div>
        </>
      ) : null}
    </div>
  )
}
