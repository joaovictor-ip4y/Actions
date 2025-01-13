module.exports = {
  collectCoverage: true,
  collectCoverageFrom: [
    'src/**/*.{js,jsx,ts,tsx}', // Inclua arquivos de código-fonte
    '!src/**/*.d.ts', // Exclua arquivos de definição de tipos
  ],
  coverageDirectory: 'coverage',
};
