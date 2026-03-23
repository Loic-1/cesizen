export default function SearchToolbar({
  searchLabel = 'Recherche',
  searchPlaceholder = 'Rechercher…',
  searchValue,
  onSearchChange,
  sortLabel = 'Trier par',
  sortValue,
  onSortChange,
  sortOptions = [],
}) {
  return (
    <div className="search-toolbar">
      <label className="form-field search-toolbar__field">
        <span>{searchLabel}</span>
        <input
          type="search"
          value={searchValue}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder={searchPlaceholder}
        />
      </label>

      <label className="form-field search-toolbar__field search-toolbar__field--sort">
        <span>{sortLabel}</span>
        <select value={sortValue} onChange={(event) => onSortChange(event.target.value)}>
          {sortOptions.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </label>
    </div>
  )
}
