using System;
using System.Globalization;
using System.IO;
using System.Net;
using System.Security.Cryptography;
using System.Text;
using System.Threading;
using System.Text.RegularExpressions;

namespace ESP32CoordinateUploader
{
    internal static class Program
    {
        private const string DefaultUrl = "https://pnkalrt.page.gd/TRACKER_SYSTEM/api/random_test_coordinate.php";
        private const string DefaultKey = "TAN";
        private const string DefaultSource = "Browser-Test-1";
        private const double DefaultLatitude = 11.562275;
        private const double DefaultLongitude = 124.399615;
        private static readonly Coordinate[] DefaultRoute =
        {
            new Coordinate(11.562275, 124.399615),
            new Coordinate(11.562385, 124.399865),
            new Coordinate(11.562500, 124.400115),
            new Coordinate(11.562610, 124.400345),
            new Coordinate(11.562720, 124.400575)
        };

        private static int Main(string[] args)
        {
            UploadOptions options;

            try
            {
                options = UploadOptions.Parse(args);
            }
            catch (Exception error)
            {
                Console.WriteLine(error.Message);
                Console.WriteLine();
                PrintHelp();
                return 1;
            }

            if (options.ShowHelp)
            {
                PrintHelp();
                return 0;
            }

            ServicePointManager.SecurityProtocol = SecurityProtocolType.Tls12;

            Console.WriteLine("ESP32 coordinate uploader simulator");
            Console.WriteLine("Server URL: " + options.Url);
            Console.WriteLine("Developer key: " + options.Key);
            Console.WriteLine("Source: " + options.Source);
            Console.WriteLine("Upload interval: " + options.IntervalSeconds + " second(s)");
            Console.WriteLine("Upload count: " + (options.Count == 0 ? "continuous" : options.Count.ToString(CultureInfo.InvariantCulture)));
            Console.WriteLine();

            var random = new Random();
            var uploadsSent = 0;
            var cookies = new CookieContainer();

            while (options.Count == 0 || uploadsSent < options.Count)
            {
                uploadsSent++;

                var coordinate = options.UseCustomCoordinate || options.Randomize
                    ? new Coordinate(options.Latitude, options.Longitude)
                    : DefaultRoute[(uploadsSent - 1) % DefaultRoute.Length];

                if (options.Randomize)
                {
                    coordinate = new Coordinate(
                        coordinate.Latitude + random.NextDouble() * 0.01 - 0.005,
                        coordinate.Longitude + random.NextDouble() * 0.01 - 0.005
                    );
                }

                UploadCoordinate(
                    options.Url,
                    Math.Round(coordinate.Latitude, 6),
                    Math.Round(coordinate.Longitude, 6),
                    options.Source,
                    options.Key,
                    uploadsSent,
                    cookies
                );

                if (options.Count == 0 || uploadsSent < options.Count)
                {
                    Thread.Sleep(TimeSpan.FromSeconds(options.IntervalSeconds));
                }
            }

            return 0;
        }

        private static void UploadCoordinate(string url, double latitude, double longitude, string source, string key, int uploadNumber, CookieContainer cookies)
        {
            var payload = string.Format(
                CultureInfo.InvariantCulture,
                "{{\"latitude\":{0:F6},\"longitude\":{1:F6},\"source\":\"{2}\"}}",
                latitude,
                longitude,
                JsonEscape(source)
            );

            try
            {
                Console.WriteLine("[" + DateTime.Now.ToString("yyyy-MM-dd hh:mm:ss tt", CultureInfo.InvariantCulture) + "] Upload #" + uploadNumber);
                Console.WriteLine("Coordinate: " + latitude.ToString("F6", CultureInfo.InvariantCulture) + ", " + longitude.ToString("F6", CultureInfo.InvariantCulture));

                PrepareInfinityFreeCookie(url, cookies);

                var requestUrl = BuildUploadUrl(url, key, source, latitude, longitude);
                var request = (HttpWebRequest)WebRequest.Create(requestUrl);

                request.Method = UsesBrowserCoordinateEndpoint(url) ? "GET" : "POST";
                request.CookieContainer = cookies;
                request.UserAgent = "ESP32CoordinateUploader/1.0";

                if (request.Method == "POST")
                {
                    var payloadBytes = Encoding.UTF8.GetBytes(payload);

                    request.ContentType = "application/json";
                    request.ContentLength = payloadBytes.Length;

                    using (var requestStream = request.GetRequestStream())
                    {
                        requestStream.Write(payloadBytes, 0, payloadBytes.Length);
                    }
                }
                else
                {
                    Console.WriteLine("GET: " + requestUrl);
                }

                using (var response = (HttpWebResponse)request.GetResponse())
                using (var stream = response.GetResponseStream())
                {
                    var responseBody = stream == null ? "" : new StreamReader(stream, Encoding.UTF8).ReadToEnd();

                    Console.WriteLine("HTTP " + (int)response.StatusCode + " " + response.StatusDescription);
                    Console.WriteLine(responseBody);
                    Console.WriteLine();
                }
            }
            catch (WebException error)
            {
                Console.WriteLine("Upload failed: " + error.Message);

                if (error.Response != null)
                {
                    using (var response = error.Response)
                    using (var stream = response.GetResponseStream())
                    {
                        if (stream != null)
                        {
                            var body = new StreamReader(stream, Encoding.UTF8).ReadToEnd();

                            if (!string.IsNullOrWhiteSpace(body))
                            {
                                Console.WriteLine(body);
                            }
                        }
                    }
                }

                Console.WriteLine();
            }
            catch (Exception error)
            {
                Console.WriteLine("Upload failed: " + error.Message);
                Console.WriteLine();
            }
        }

        private static string JsonEscape(string value)
        {
            return value.Replace("\\", "\\\\").Replace("\"", "\\\"");
        }

        private static bool UsesBrowserCoordinateEndpoint(string url)
        {
            return url.IndexOf("random_test_coordinate.php", StringComparison.OrdinalIgnoreCase) >= 0;
        }

        private static string BuildUploadUrl(string url, string key, string source, double latitude, double longitude)
        {
            if (!UsesBrowserCoordinateEndpoint(url))
            {
                return url;
            }

            var separator = url.Contains("?") ? "&" : "?";

            return url
                + separator
                + "key=" + Uri.EscapeDataString(key)
                + "&source=" + Uri.EscapeDataString(source)
                + "&latitude=" + latitude.ToString("F6", CultureInfo.InvariantCulture)
                + "&longitude=" + longitude.ToString("F6", CultureInfo.InvariantCulture);
        }

        private static void PrepareInfinityFreeCookie(string url, CookieContainer cookies)
        {
            var request = (HttpWebRequest)WebRequest.Create(url);
            request.Method = "GET";
            request.CookieContainer = cookies;
            request.UserAgent = "ESP32CoordinateUploader/1.0";

            using (var response = (HttpWebResponse)request.GetResponse())
            using (var stream = response.GetResponseStream())
            {
                var body = stream == null ? "" : new StreamReader(stream, Encoding.UTF8).ReadToEnd();

                if (!body.Contains("__test") || !body.Contains("slowAES.decrypt"))
                {
                    return;
                }

                var cookieValue = ComputeInfinityFreeCookie(body);

                if (cookieValue.Length > 0)
                {
                    var uri = new Uri(url);
                    cookies.Add(uri, new Cookie("__test", cookieValue, "/"));
                    Console.WriteLine("InfinityFree validation cookie prepared.");
                }
            }
        }

        private static string ComputeInfinityFreeCookie(string challengeHtml)
        {
            var matches = Regex.Matches(challengeHtml, "toNumbers\\(\"([0-9a-fA-F]+)\"\\)");

            if (matches.Count < 3)
            {
                return "";
            }

            var key = HexToBytes(matches[0].Groups[1].Value);
            var iv = HexToBytes(matches[1].Groups[1].Value);
            var cipher = HexToBytes(matches[2].Groups[1].Value);

            using (var aes = Aes.Create())
            {
                aes.Mode = CipherMode.CBC;
                aes.Padding = PaddingMode.None;
                aes.Key = key;
                aes.IV = iv;

                using (var decryptor = aes.CreateDecryptor())
                {
                    var decrypted = decryptor.TransformFinalBlock(cipher, 0, cipher.Length);
                    return BytesToHex(decrypted);
                }
            }
        }

        private static byte[] HexToBytes(string hex)
        {
            var bytes = new byte[hex.Length / 2];

            for (var index = 0; index < bytes.Length; index++)
            {
                bytes[index] = byte.Parse(hex.Substring(index * 2, 2), NumberStyles.HexNumber, CultureInfo.InvariantCulture);
            }

            return bytes;
        }

        private static string BytesToHex(byte[] bytes)
        {
            var builder = new StringBuilder(bytes.Length * 2);

            foreach (var value in bytes)
            {
                builder.Append(value.ToString("x2", CultureInfo.InvariantCulture));
            }

            return builder.ToString();
        }

        private static void PrintHelp()
        {
            Console.WriteLine("ESP32 coordinate uploader simulator");
            Console.WriteLine();
            Console.WriteLine("Usage:");
            Console.WriteLine("  ESP32CoordinateUploader.exe [options]");
            Console.WriteLine();
            Console.WriteLine("Options:");
            Console.WriteLine("  --url <url>          API URL. Defaults to the pnkalrt random-test endpoint.");
            Console.WriteLine("  --key <value>        Developer key for random_test_coordinate.php. Defaults to TAN.");
            Console.WriteLine("  --lat <number>       Latitude. Defaults to the first Naval/BiPSU route point.");
            Console.WriteLine("  --lng <number>       Longitude. Defaults to the first Naval/BiPSU route point.");
            Console.WriteLine("  --source <name>      Device source. Defaults to Browser-Test-1.");
            Console.WriteLine("  --interval <seconds> Seconds between uploads. Defaults to 5.");
            Console.WriteLine("  --count <number>     Number of uploads. Use 0 for continuous. Defaults to 5.");
            Console.WriteLine("  --random             Slightly changes coordinates each upload.");
            Console.WriteLine("  --help               Show this help screen.");
            Console.WriteLine();
            Console.WriteLine("Example:");
            Console.WriteLine("  ESP32CoordinateUploader.exe");
            Console.WriteLine("  ESP32CoordinateUploader.exe --url https://pnkalrt.page.gd/TRACKER_SYSTEM/api/random_test_coordinate.php --key TAN --source Browser-Test-1");
        }

        private struct Coordinate
        {
            public readonly double Latitude;
            public readonly double Longitude;

            public Coordinate(double latitude, double longitude)
            {
                Latitude = latitude;
                Longitude = longitude;
            }
        }

        private sealed class UploadOptions
        {
            public string Url = DefaultUrl;
            public string Key = DefaultKey;
            public double Latitude = DefaultLatitude;
            public double Longitude = DefaultLongitude;
            public string Source = DefaultSource;
            public int IntervalSeconds = 5;
            public int Count = 5;
            public bool Randomize;
            public bool ShowHelp;
            public bool UseCustomCoordinate;

            public static UploadOptions Parse(string[] args)
            {
                var options = new UploadOptions();

                for (var index = 0; index < args.Length; index++)
                {
                    var argument = args[index].Trim();

                    switch (argument)
                    {
                        case "--url":
                            options.Url = ReadNext(args, ref index, argument);
                            break;

                        case "--key":
                            options.Key = ReadNext(args, ref index, argument);
                            break;

                        case "--lat":
                            options.Latitude = ReadDouble(args, ref index, argument);
                            options.UseCustomCoordinate = true;
                            break;

                        case "--lng":
                        case "--lon":
                            options.Longitude = ReadDouble(args, ref index, argument);
                            options.UseCustomCoordinate = true;
                            break;

                        case "--source":
                            options.Source = ReadNext(args, ref index, argument);
                            break;

                        case "--interval":
                            options.IntervalSeconds = Math.Max(1, ReadInt(args, ref index, argument));
                            break;

                        case "--count":
                            options.Count = Math.Max(0, ReadInt(args, ref index, argument));
                            break;

                        case "--random":
                            options.Randomize = true;
                            break;

                        case "--help":
                        case "-h":
                        case "/?":
                            options.ShowHelp = true;
                            break;

                        default:
                            throw new ArgumentException("Unknown option: " + argument);
                    }
                }

                return options;
            }

            private static string ReadNext(string[] args, ref int index, string optionName)
            {
                if (index + 1 >= args.Length)
                {
                    throw new ArgumentException(optionName + " needs a value.");
                }

                index++;
                return args[index];
            }

            private static double ReadDouble(string[] args, ref int index, string optionName)
            {
                var value = ReadNext(args, ref index, optionName);
                double parsed;

                if (!double.TryParse(value, NumberStyles.Float, CultureInfo.InvariantCulture, out parsed))
                {
                    throw new ArgumentException(optionName + " must be a number.");
                }

                return parsed;
            }

            private static int ReadInt(string[] args, ref int index, string optionName)
            {
                var value = ReadNext(args, ref index, optionName);
                int parsed;

                if (!int.TryParse(value, NumberStyles.Integer, CultureInfo.InvariantCulture, out parsed))
                {
                    throw new ArgumentException(optionName + " must be a whole number.");
                }

                return parsed;
            }
        }
    }
}
