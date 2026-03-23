import { useEffect, useMemo, useState } from 'react'

const PRESETS = [
  { label: '615', inhale: 6, hold: 1, exhale: 5, cycles: 6 },
  { label: '365', inhale: 3, hold: 0, exhale: 6, cycles: 5 },
  { label: '478', inhale: 4, hold: 7, exhale: 8, cycles: 4 },
]

const PHASES = [
  { key: 'inhale', label: 'Inspiration' },
  { key: 'hold', label: 'Apnée' },
  { key: 'exhale', label: 'Expiration' },
]

const initialConfig = {
  inhale: 6,
  hold: 1,
  exhale: 5,
  cycles: 6,
}

const MIN_BUBBLE_SCALE = 0.76
const MAX_BUBBLE_SCALE = 1.1

function getPhaseDuration(config, phaseKey) {
  return Number(config[phaseKey] ?? 0)
}

function getInitialDuration(config) {
  return Math.max(1, getPhaseDuration(config, PHASES[0].key))
}

function getBreathingStyle({ isCompleted, isRunning, phaseKey, phaseDuration }) {
  if (isCompleted || !isRunning) {
    return {
      transform: `scale(${MIN_BUBBLE_SCALE})`,
      transitionDuration: '0.4s',
    }
  }

  if (phaseKey === 'hold') {
    return {
      transform: `scale(${MAX_BUBBLE_SCALE})`,
      transitionDuration: '0s',
    }
  }

  return {
    transform: `scale(${phaseKey === 'inhale' ? MAX_BUBBLE_SCALE : MIN_BUBBLE_SCALE})`,
    transitionDuration: `${Math.max(0.2, phaseDuration)}s`,
  }
}

export default function CardiacCoherencePage() {
  const [config, setConfig] = useState(initialConfig)
  const [phaseIndex, setPhaseIndex] = useState(0)
  const [remainingSeconds, setRemainingSeconds] = useState(initialConfig.inhale)
  const [currentCycle, setCurrentCycle] = useState(1)
  const [isRunning, setIsRunning] = useState(false)
  const [isCompleted, setIsCompleted] = useState(false)

  const currentPhase = PHASES[phaseIndex]
  const currentPhaseDuration = getPhaseDuration(config, currentPhase.key)
  const totalDurationPerCycle = useMemo(
    () => Math.max(0, config.inhale + config.hold + config.exhale),
    [config],
  )

  useEffect(() => {
    if (!isRunning) {
      return undefined
    }

    const timer = window.setInterval(() => {
      setRemainingSeconds((currentRemaining) => {
        if (currentRemaining > 1) {
          return currentRemaining - 1
        }

        const isLastPhase = phaseIndex === PHASES.length - 1
        const nextCycle = currentCycle + (isLastPhase ? 1 : 0)

        if (isLastPhase && currentCycle >= config.cycles) {
          window.clearInterval(timer)
          setIsRunning(false)
          setIsCompleted(true)
          return 0
        }

        const nextPhaseIndex = isLastPhase ? 0 : phaseIndex + 1
        const nextPhaseKey = PHASES[nextPhaseIndex].key
        const nextDuration = Math.max(1, getPhaseDuration(config, nextPhaseKey))

        setPhaseIndex(nextPhaseIndex)
        setCurrentCycle(isLastPhase ? nextCycle : currentCycle)

        return nextDuration
      })
    }, 1000)

    return () => window.clearInterval(timer)
  }, [config, currentCycle, isRunning, phaseIndex])

  useEffect(() => {
    if (isRunning || isCompleted) {
      return
    }

    setPhaseIndex(0)
    setRemainingSeconds(getInitialDuration(config))
    setCurrentCycle(1)
    setIsCompleted(false)
  }, [config.cycles, config.exhale, config.hold, config.inhale, isCompleted, isRunning])

  function updateConfigField(field, value) {
    const numericValue = Math.max(0, Number(value) || 0)
    const normalizedValue = field === 'cycles' ? Math.max(1, numericValue) : numericValue

    setConfig((current) => ({
      ...current,
      [field]: normalizedValue,
    }))
  }

  function applyPreset(preset) {
    setIsRunning(false)
    setConfig({
      inhale: preset.inhale,
      hold: preset.hold,
      exhale: preset.exhale,
      cycles: preset.cycles,
    })
  }

  function handleStart() {
    setIsCompleted(false)
    setIsRunning(true)
    setPhaseIndex(0)
    setCurrentCycle(1)
    setRemainingSeconds(getInitialDuration(config))
  }

  function handlePause() {
    setIsRunning(false)
  }

  function handleReset() {
    setIsRunning(false)
    setIsCompleted(false)
    setPhaseIndex(0)
    setCurrentCycle(1)
    setRemainingSeconds(getInitialDuration(config))
  }

  const phaseProgress =
    currentPhaseDuration > 0
      ? isCompleted
        ? 100
        : Math.min(100, ((currentPhaseDuration - remainingSeconds + 1) / currentPhaseDuration) * 100)
      : 0

  const breathingStyle = getBreathingStyle({
    isCompleted,
    isRunning,
    phaseKey: currentPhase.key,
    phaseDuration: currentPhaseDuration,
  })

  return (
    <main className="page page--top">
      <section className="page__content page__content--wide page__content--left">
        <p className="eyebrow">Bien-être</p>
        <h1>Exercice de cohérence cardiaque</h1>
        <p>
          Configurez un exercice de respiration autour de trois temps : inspiration, apnée et
          expiration, puis lancez votre séance.
        </p>

        <div className="coherence-layout">
          <article className="detail-card">
            <h2>Réglages</h2>

            <div className="coherence-presets">
              {PRESETS.map((preset) => (
                <button
                  key={preset.label}
                  type="button"
                  className="button button--ghost"
                  onClick={() => applyPreset(preset)}
                  disabled={isRunning}
                >
                  Exercice {preset.label}
                </button>
              ))}
            </div>

            <form className="form-card form-card--compact">
              <label className="form-field">
                <span>Inspiration (secondes)</span>
                <input
                  type="number"
                  min="1"
                  value={config.inhale}
                  onChange={(event) => updateConfigField('inhale', event.target.value)}
                  disabled={isRunning}
                />
              </label>

              <label className="form-field">
                <span>Apnée (secondes)</span>
                <input
                  type="number"
                  min="0"
                  value={config.hold}
                  onChange={(event) => updateConfigField('hold', event.target.value)}
                  disabled={isRunning}
                />
              </label>

              <label className="form-field">
                <span>Expiration (secondes)</span>
                <input
                  type="number"
                  min="1"
                  value={config.exhale}
                  onChange={(event) => updateConfigField('exhale', event.target.value)}
                  disabled={isRunning}
                />
              </label>

              <label className="form-field">
                <span>Nombre de cycles</span>
                <input
                  type="number"
                  min="1"
                  value={config.cycles}
                  onChange={(event) => updateConfigField('cycles', event.target.value)}
                  disabled={isRunning}
                />
              </label>
            </form>

            <div className="page__actions page__actions--left">
              {!isRunning ? (
                <button type="button" className="button button--primary" onClick={handleStart}>
                  Lancer l'exercice
                </button>
              ) : (
                <button type="button" className="button button--primary" onClick={handlePause}>
                  Mettre en pause
                </button>
              )}

              <button type="button" className="button button--ghost" onClick={handleReset}>
                Réinitialiser
              </button>
            </div>
          </article>

          <article className="detail-card coherence-session">
            <h2>Séance en cours</h2>

            <div className="coherence-bubble-wrap" aria-hidden="true">
              <div
                className={
                  isRunning
                    ? 'coherence-bubble coherence-bubble--active'
                    : 'coherence-bubble'
                }
                style={breathingStyle}
              />
            </div>

            <div className="coherence-session__hero">
              <p className="coherence-session__phase">{isCompleted ? 'Terminé' : currentPhase.label}</p>
              <p className="coherence-session__timer">{remainingSeconds}s</p>
              <p className="coherence-session__cycle">
                Cycle {Math.min(currentCycle, config.cycles)} sur {config.cycles}
              </p>
            </div>

            <div className="coherence-session__progress" aria-hidden="true">
              <div
                className="coherence-session__progress-bar"
                style={{ width: `${phaseProgress}%` }}
              />
            </div>

            <dl className="detail-list">
              <div>
                <dt>Programme</dt>
                <dd>
                  {config.inhale} - {config.hold} - {config.exhale}
                </dd>
              </div>
              <div>
                <dt>Durée d'un cycle</dt>
                <dd>{totalDurationPerCycle} secondes</dd>
              </div>
              <div>
                <dt>État</dt>
                <dd>
                  {isCompleted
                    ? 'Exercice terminé'
                    : isRunning
                      ? 'En cours'
                      : 'Prêt à démarrer'}
                </dd>
              </div>
            </dl>

            <p className="form-helper">
              Exemple : l'exercice 615 demande d'inspirer 6 secondes, de retenir 1 seconde puis
              d'expirer 5 secondes.
            </p>
          </article>
        </div>
      </section>
    </main>
  )
}
